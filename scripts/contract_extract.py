"""Extração segura de ZIP para artefatos de contrato OpenAPI.

Aplica sanitização de arquivo em modo fail-closed antes de confiar nos bytes:
- Apenas arquivos esperados na raiz via allowlist (padrão: openapi.json).
- Rejeita caminhos absolutos, traversal de diretório, letras de unidade, barras invertidas.
- Rejeita nomes duplicados (normalizados), links simbólicos e diretórios.
- Rejeita entradas criptografadas, compressão não suportada e overflow de tamanho.
- Aplica limites reais de streaming (não apenas metadados): teto total e por arquivo.
- Rejeita URLs de $ref remotos no contrato extraído para evitar SSRF.
"""

from __future__ import annotations

from dataclasses import dataclass
import hashlib
import io
import zipfile

DEFAULT_EXPECTED_FILES: frozenset[str] = frozenset({"openapi.json"})
DEFAULT_MAX_FILES: int = 8
DEFAULT_MAX_TOTAL_BYTES: int = 10 * 1024 * 1024
DEFAULT_MAX_FILE_BYTES: int = 10 * 1024 * 1024

REMOTE_REF_MARKERS: tuple[str, ...] = (
    '"http://',
    '"https://',
    "'http://",
    "'https://",
)


class ArchiveSafetyError(Exception):
    """Lançada quando a extração segura do arquivo falha em modo fail-closed."""


@dataclass(frozen=True)
class ExtractionPolicy:
    """Política imutável para extração segura de arquivos compactados."""

    expected_files: frozenset[str] = DEFAULT_EXPECTED_FILES
    max_files: int = DEFAULT_MAX_FILES
    max_total_bytes: int = DEFAULT_MAX_TOTAL_BYTES
    max_file_bytes: int = DEFAULT_MAX_FILE_BYTES


@dataclass(frozen=True)
class ExtractionResult:
    """Resultado imutável de uma extração segura."""

    files: tuple[str, ...]
    total_bytes: int
    file_sha256: str


def normalize_entry_name(raw: str) -> str:
    """Normaliza o nome da entrada no ZIP para um caminho POSIX relativo canônico."""
    cleaned = raw.replace("\\", "/").strip()
    if cleaned.startswith("/"):
        raise ArchiveSafetyError(f"Caminho absoluto de entrada rejeitado: {raw!r}")
    if len(cleaned) >= 2 and cleaned[1] == ":" and cleaned[0].isalpha():
        raise ArchiveSafetyError(f"Entrada com letra de unidade rejeitada: {raw!r}")
    parts: list[str] = []
    for part in cleaned.split("/"):
        if part in ("", "."):
            continue
        if part == "..":
            raise ArchiveSafetyError(f"Entrada com traversal rejeitada: {raw!r}")
        parts.append(part)
    normalized = "/".join(parts)
    if normalized == "":
        raise ArchiveSafetyError(f"Caminho de entrada vazio rejeitado: {raw!r}")
    if normalized.startswith("__MACOSX/"):
        raise ArchiveSafetyError(f"Entrada de metadados rejeitada: {raw!r}")
    return normalized


def validate_entry_metadata(info: zipfile.ZipInfo, policy: ExtractionPolicy) -> str:
    """Valida o cabeçalho de uma entrada no ZIP; retorna o nome normalizado."""
    if info.is_dir():
        raise ArchiveSafetyError(f"Entrada de diretório rejeitada: {info.filename!r}")
    if info.filename.endswith("/"):
        raise ArchiveSafetyError(f"Entrada de diretório rejeitada: {info.filename!r}")
    flag_bits = info.flag_bits
    if flag_bits & 0x1:
        raise ArchiveSafetyError(f"Entrada criptografada rejeitada: {info.filename!r}")
    if info.compress_type not in (zipfile.ZIP_STORED, zipfile.ZIP_DEFLATED):
        raise ArchiveSafetyError(f"Compressão não suportada rejeitada: {info.filename!r}")
    normalized = normalize_entry_name(info.filename)
    if "/" in normalized:
        raise ArchiveSafetyError(f"Caminho aninhado rejeitado (apenas arquivos na raiz): {normalized!r}")
    if normalized not in policy.expected_files:
        raise ArchiveSafetyError(f"Arquivo inesperado rejeitado: {normalized!r}")
    if info.file_size > policy.max_file_bytes:
        raise ArchiveSafetyError(f"Entrada excede o limite por arquivo: {normalized!r}")
    external_attr = (info.external_attr >> 16) & 0xFFFF
    if external_attr & 0o120000 == 0o120000:
        raise ArchiveSafetyError(f"Link simbólico rejeitado: {normalized!r}")
    return normalized


def stream_entry_bytes(
    archive: zipfile.ZipFile, info: zipfile.ZipInfo, policy: ExtractionPolicy
) -> bytes:
    """Lê bytes da entrada aplicando limites reais de streaming."""
    chunks: list[bytes] = []
    total = 0
    with archive.open(info, "r") as handle:
        while True:
            chunk = handle.read(65536)
            if not chunk:
                break
            total += len(chunk)
            if total > policy.max_file_bytes:
                raise ArchiveSafetyError(f"Stream excede o limite por arquivo: {info.filename!r}")
            chunks.append(chunk)
    return b"".join(chunks)


def reject_remote_refs(payload: bytes, filename: str) -> None:
    """Rejeita referências remotas $ref no contrato para evitar SSRF."""
    try:
        text = payload.decode("utf-8")
    except UnicodeDecodeError as exc:
        raise ArchiveSafetyError(f"Contrato não-UTF-8 rejeitado: {filename!r}") from exc
    lowered = text.lower()
    for marker in REMOTE_REF_MARKERS:
        if marker in lowered:
            raise ArchiveSafetyError(f"Referência remota $ref rejeitada em {filename!r}: {marker}")


def safe_extract_archive(
    archive_bytes: bytes, policy: ExtractionPolicy | None = None
) -> ExtractionResult:
    """Extrai arquivos esperados dos bytes do ZIP sob política fail-closed."""
    active = policy or ExtractionPolicy()
    if len(archive_bytes) == 0:
        raise ArchiveSafetyError("Arquivo compactado vazio rejeitado")
    if len(archive_bytes) > active.max_total_bytes + 1024 * 1024:
        raise ArchiveSafetyError("Arquivo compactado excede o limite total")
    try:
        handle = zipfile.ZipFile(io.BytesIO(archive_bytes), "r")
    except zipfile.BadZipFile as exc:
        raise ArchiveSafetyError(f"ZIP inválido rejeitado: {exc}") from exc
    with handle:
        infos = handle.infolist()
        if len(infos) == 0:
            raise ArchiveSafetyError("Arquivo compactado sem entradas rejeitado")
        if len(infos) > active.max_files:
            raise ArchiveSafetyError("Contagem de arquivos no ZIP excede o limite")
        seen: set[str] = set()
        total = 0
        primary_hash = ""
        primary_name = ""
        for info in infos:
            normalized = validate_entry_metadata(info, active)
            if normalized in seen:
                raise ArchiveSafetyError(f"Entrada duplicada rejeitada: {normalized!r}")
            seen.add(normalized)
            payload = stream_entry_bytes(handle, info, active)
            total += len(payload)
            if total > active.max_total_bytes:
                raise ArchiveSafetyError("Total extraído excede o limite permitido")
            reject_remote_refs(payload, normalized)
            digest = hashlib.sha256(payload).hexdigest()
            if primary_name == "":
                primary_name = normalized
                primary_hash = digest
        missing = set(active.expected_files) - seen
        if missing:
            raise ArchiveSafetyError(f"Arquivos esperados ausentes: {sorted(missing)!r}")
        names = tuple(sorted(seen))
        return ExtractionResult(files=names, total_bytes=total, file_sha256=primary_hash)


def read_bounded_archive(path: str, max_bytes: int = DEFAULT_MAX_TOTAL_BYTES + 1024 * 1024) -> bytes:
    """Lê bytes do arquivo compactado aplicando limite nominal antes do parsing."""
    from pathlib import Path as FsPath

    data = FsPath(path).read_bytes()
    if len(data) > max_bytes:
        raise ArchiveSafetyError(f"Arquivo compactado excede o limite permitido: {path!r}")
    return data
