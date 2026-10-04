"""Safe ZIP extraction for OpenAPI contract artifacts.

Enforces fail-closed archive sanitization before any spec bytes are trusted:
- Expected root files allowlist only (default: openapi.json).
- Rejects absolute paths, parent traversal, drive letters, backslashes.
- Rejects duplicate names (normalized), symlinks, directories escaping root.
- Rejects encrypted entries, unsupported compression, size overflow.
- Enforces actual stream bounds (not only metadata): total uncompressed cap
  and per-file cap, measured while streaming.
- Rejects remote $ref URLs in the extracted contract to avoid SSRF before
  validator/codegen (http/https refs fail closed).

Digest decision (dual-digest separation):
- The provider archive digest (archive_sha256, ZIP from GitHub Actions) and
  the extracted spec file hash (artifact_file_sha256) are distinct. The file
  hash is recomputed from the extracted spec stream, never copied from the
  archive digest. The upload returns id/digest AFTER the archive is built, so
  the receipt cannot be inside the same ZIP (circular self-hash avoided by
  constructing the receipt via operator fetch AFTER trusted metadata + raw ZIP
  verification, or via a separate receipt channel).
"""

from __future__ import annotations

from dataclasses import dataclass
import hashlib
import io
from pathlib import PurePosixPath
import zipfile


DEFAULT_EXPECTED_FILES: frozenset[str] = frozenset({'openapi.json'})
DEFAULT_MAX_FILES: int = 8
DEFAULT_MAX_TOTAL_BYTES: int = 10 * 1024 * 1024
DEFAULT_MAX_FILE_BYTES: int = 10 * 1024 * 1024

REMOTE_REF_MARKERS: tuple[str, ...] = ('"http://', '"https://', "'http://", "'https://")


class ArchiveSafetyError(Exception):
    """Raised when archive extraction fails closed."""


@dataclass(frozen=True)
class ExtractionPolicy:
    """Immutable policy for safe archive extraction."""

    expected_files: frozenset[str] = DEFAULT_EXPECTED_FILES
    max_files: int = DEFAULT_MAX_FILES
    max_total_bytes: int = DEFAULT_MAX_TOTAL_BYTES
    max_file_bytes: int = DEFAULT_MAX_FILE_BYTES


@dataclass(frozen=True)
class ExtractionResult:
    """Immutable result of a safe extraction."""

    files: tuple[str, ...]
    total_bytes: int
    file_sha256: str


def normalize_entry_name(raw: str) -> str:
    """Normalize a ZIP entry name to a canonical relative POSIX path."""
    cleaned = raw.replace('\\', '/').strip()
    if cleaned.startswith('/'):
        raise ArchiveSafetyError(f'Absolute entry path rejected: {raw!r}')
    if len(cleaned) >= 2 and cleaned[1] == ':' and cleaned[0].isalpha():
        raise ArchiveSafetyError(f'Drive-letter entry rejected: {raw!r}')
    parts: list[str] = []
    for part in cleaned.split('/'):
        if part in ('', '.'):
            continue
        if part == '..':
            raise ArchiveSafetyError(f'Traversal entry rejected: {raw!r}')
        parts.append(part)
    normalized = '/'.join(parts)
    if normalized == '':
        raise ArchiveSafetyError(f'Empty entry path rejected: {raw!r}')
    if normalized.startswith('__MACOSX/'):
        raise ArchiveSafetyError(f'Metadata entry rejected: {raw!r}')
    return normalized


def validate_entry_metadata(info: zipfile.ZipInfo, policy: ExtractionPolicy) -> str:
    """Validate a single ZIP entry header; returns normalized name."""
    if info.is_dir():
        raise ArchiveSafetyError(f'Directory entry rejected: {info.filename!r}')
    if info.filename.endswith('/'):
        raise ArchiveSafetyError(f'Directory entry rejected: {info.filename!r}')
    flag_bits = info.flag_bits
    if flag_bits & 0x1:
        raise ArchiveSafetyError(f'Encrypted entry rejected: {info.filename!r}')
    if info.compress_type not in (zipfile.ZIP_STORED, zipfile.ZIP_DEFLATED):
        raise ArchiveSafetyError(f'Unsupported compression rejected: {info.filename!r}')
    normalized = normalize_entry_name(info.filename)
    if '/' in normalized:
        raise ArchiveSafetyError(f'Nested path rejected (root files only): {normalized!r}')
    if normalized not in policy.expected_files:
        raise ArchiveSafetyError(f'Unexpected file rejected: {normalized!r}')
    if info.file_size > policy.max_file_bytes:
        raise ArchiveSafetyError(f'Entry exceeds per-file bound: {normalized!r}')
    external_attr = (info.external_attr >> 16) & 0xFFFF
    if external_attr & 0o120000 == 0o120000:
        raise ArchiveSafetyError(f'Symlink entry rejected: {normalized!r}')
    return normalized


def stream_entry_bytes(
    archive: zipfile.ZipFile, info: zipfile.ZipInfo, policy: ExtractionPolicy
) -> bytes:
    """Stream entry bytes enforcing actual (not metadata-only) bounds."""
    chunks: list[bytes] = []
    total = 0
    with archive.open(info, 'r') as handle:
        while True:
            chunk = handle.read(65536)
            if not chunk:
                break
            total += len(chunk)
            if total > policy.max_file_bytes:
                raise ArchiveSafetyError(f'Stream exceeds per-file bound: {info.filename!r}')
            chunks.append(chunk)
    return b''.join(chunks)


def reject_remote_refs(payload: bytes, filename: str) -> None:
    """Reject remote URL references in the contract to avoid SSRF."""
    try:
        text = payload.decode('utf-8')
    except UnicodeDecodeError as exc:
        raise ArchiveSafetyError(f'Non-UTF8 contract rejected: {filename!r}') from exc
    lowered = text.lower()
    for marker in REMOTE_REF_MARKERS:
        if marker in lowered:
            raise ArchiveSafetyError(f'Remote $ref rejected in {filename!r}: {marker}')


def safe_extract_archive(
    archive_bytes: bytes, policy: ExtractionPolicy | None = None
) -> ExtractionResult:
    """Extract expected files from ZIP bytes under fail-closed policy."""
    active = policy or ExtractionPolicy()
    if len(archive_bytes) == 0:
        raise ArchiveSafetyError('Empty archive rejected')
    if len(archive_bytes) > active.max_total_bytes + 1024 * 1024:
        raise ArchiveSafetyError('Archive exceeds total bound')
    try:
        handle = zipfile.ZipFile(io.BytesIO(archive_bytes), 'r')
    except zipfile.BadZipFile as exc:
        raise ArchiveSafetyError(f'Invalid ZIP rejected: {exc}') from exc
    with handle:
        infos = handle.infolist()
        if len(infos) == 0:
            raise ArchiveSafetyError('Archive with no entries rejected')
        if len(infos) > active.max_files:
            raise ArchiveSafetyError('Archive file count exceeds bound')
        seen: set[str] = set()
        total = 0
        primary_hash = ''
        primary_name = ''
        for info in infos:
            normalized = validate_entry_metadata(info, active)
            if normalized in seen:
                raise ArchiveSafetyError(f'Duplicate entry rejected: {normalized!r}')
            seen.add(normalized)
            payload = stream_entry_bytes(handle, info, active)
            total += len(payload)
            if total > active.max_total_bytes:
                raise ArchiveSafetyError('Extracted total exceeds bound')
            reject_remote_refs(payload, normalized)
            digest = hashlib.sha256(payload).hexdigest()
            if primary_name == '':
                primary_name = normalized
                primary_hash = digest
        missing = set(active.expected_files) - seen
        if missing:
            raise ArchiveSafetyError(f'Missing expected files: {sorted(missing)!r}')
        names = tuple(sorted(seen))
        return ExtractionResult(files=names, total_bytes=total, file_sha256=primary_hash)


def read_bounded_archive(path: str, max_bytes: int = DEFAULT_MAX_TOTAL_BYTES + 1024 * 1024) -> bytes:
    """Read archive bytes enforcing a nominal bound before parsing."""
    from pathlib import Path as FsPath

    data = FsPath(path).read_bytes()
    if len(data) > max_bytes:
        raise ArchiveSafetyError(f'Archive file exceeds bound: {path!r}')
    return data
