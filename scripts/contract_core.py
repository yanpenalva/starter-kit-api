"""Core validation engine and domain primitives for OpenAPI contract provenance.

Responsibility:
Enforces fail-closed validation rules, schema definitions, bounds,
digest recomputation, and expiration policies for OpenAPI contract artifacts.
This module is a foundation layer with zero sibling dependencies.
"""

from __future__ import annotations

from dataclasses import dataclass, replace
from datetime import datetime, timedelta, timezone
import hashlib
import json
from pathlib import Path
import re
from typing import Any

DEFAULT_OWNER: str = "yanpenalva"
DEFAULT_REPO: str = "starter-kit-api"
DEFAULT_BRANCH: str = "main"
DEFAULT_WORKFLOW_NAME: str = "OpenAPI Contract"
DEFAULT_WORKFLOW_PATH: str = ".github/workflows/openapi.yml"
DEFAULT_ALLOWED_EVENTS: frozenset[str] = frozenset({"workflow_dispatch", "release", "workflow_run"})
DEFAULT_MAX_AGE_DAYS: int = 90
CLOCK_SKEW_TOLERANCE_SECONDS: int = 300  # 5 minutes

MAX_RECEIPT_BYTES: int = 64 * 1024  # 64 KiB (S6)
MAX_ARTIFACT_BYTES: int = 10 * 1024 * 1024  # 10 MiB (S6)

MODE_DOWNLOAD_FRESH: str = "download-fresh"
MODE_VERIFY_COMMITTED: str = "verify-committed"
VALID_MODES: frozenset[str] = frozenset({MODE_DOWNLOAD_FRESH, MODE_VERIFY_COMMITTED})

SHA40_PATTERN: re.Pattern[str] = re.compile(r"^[0-9a-fA-F]{40}$")
SHA256_PATTERN: re.Pattern[str] = re.compile(r"^[0-9a-fA-F]{64}$")
OWNER_REPO_PATTERN: re.Pattern[str] = re.compile(r"^[A-Za-z0-9_.-]+$")

REQUIRED_RECEIPT_FIELDS: tuple[str, ...] = (
    "workflow_name",
    "workflow_path",
    "workflow_id",
    "run_id",
    "run_url",
    "artifact_id",
    "artifact_name",
    "commit_sha",
    "head_branch",
    "event",
    "created_at",
    "archive_sha256",
    "artifact_file_sha256",
)


class ProvenanceVerificationError(Exception):
    """Raised when artifact provenance verification fails closed."""


@dataclass(frozen=True)
class VerificationPolicy:
    """Policy grouping trusted repository, workflow, and commit identity expectations."""

    expected_sha: str
    expected_branch: str = DEFAULT_BRANCH
    expected_workflow_name: str = DEFAULT_WORKFLOW_NAME
    expected_workflow_path: str = DEFAULT_WORKFLOW_PATH
    expected_artifact_name: str | None = None
    expected_workflow_id: int | None = None
    allowed_events: frozenset[str] = DEFAULT_ALLOWED_EVENTS
    owner: str = DEFAULT_OWNER
    repo: str = DEFAULT_REPO


def check_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    """Object pairs hook for json.loads that rejects duplicate keys."""
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ProvenanceVerificationError(f"Duplicate JSON key in receipt: '{key}'")
        result[key] = value
    return result


def read_bounded_bytes(file_path: Path, max_bytes: int, file_desc: str) -> bytes:
    """Read a file bounded by maximum allowed bytes."""
    if not file_path.is_file():
        raise ProvenanceVerificationError(f"{file_desc} file not found: {file_path}")
    size = file_path.stat().st_size
    if size > max_bytes:
        raise ProvenanceVerificationError(
            f"{file_desc} file size ({size} bytes) exceeds nominal limit of {max_bytes} bytes"
        )
    with file_path.open("rb") as stream:
        content = stream.read(max_bytes + 1)
    if len(content) > max_bytes:
        raise ProvenanceVerificationError(
            f"{file_desc} stream exceeded maximum limit of {max_bytes} bytes"
        )
    return content


def compute_file_sha256(file_path: Path) -> str:
    """Compute SHA256 hex digest of a file reading in 64KiB chunks."""
    hasher = hashlib.sha256()
    with file_path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(65536), b""):
            hasher.update(chunk)
    return hasher.hexdigest().lower()


def validate_commit_sha(sha: str, field_name: str = "commit_sha") -> str:
    """Validate that a commit SHA is an exact 40-character hexadecimal string."""
    cleaned = str(sha).strip().lower()
    if not SHA40_PATTERN.fullmatch(cleaned):
        raise ProvenanceVerificationError(
            f"Invalid {field_name} '{sha}': must be exact 40-character hex SHA, not 'latest', branch, or tag"
        )
    return cleaned


def validate_sha256_digest(digest: str, field_name: str) -> str:
    """Validate that a digest is an exact 64-character hexadecimal string."""
    cleaned = str(digest).strip().lower()
    if not SHA256_PATTERN.fullmatch(cleaned):
        raise ProvenanceVerificationError(
            f"Invalid {field_name} '{digest}': must be exact 64-character hexadecimal SHA256 digest"
        )
    return cleaned


def parse_utc_timestamp(timestamp_str: str) -> datetime:
    """Parse and validate ISO 8601 timestamp string into UTC datetime."""
    cleaned = str(timestamp_str).strip()
    if not cleaned:
        raise ProvenanceVerificationError("Receipt created_at timestamp cannot be empty")
    try:
        normalized = cleaned.replace("Z", "+00:00")
        dt = datetime.fromisoformat(normalized)
        if dt.tzinfo is None:
            raise ProvenanceVerificationError(f"Timestamp '{timestamp_str}' must include timezone offset")
        return dt.astimezone(timezone.utc)
    except (ValueError, TypeError) as exc:
        raise ProvenanceVerificationError(
            f"Invalid ISO 8601 created_at timestamp '{timestamp_str}': {exc}"
        ) from exc


def validate_strict_int_id(value: Any, field_name: str) -> int:
    """Validate that an ID is strictly a positive JSON integer (reject bool, float, str)."""
    if isinstance(value, bool):
        raise ProvenanceVerificationError(f"{field_name} must be a strict integer, got bool ({value})")
    if not isinstance(value, int):
        raise ProvenanceVerificationError(
            f"{field_name} must be a strict integer, got {type(value).__name__} ({value!r})"
        )
    if value <= 0:
        raise ProvenanceVerificationError(f"{field_name} must be a positive integer, got {value}")
    return value


def validate_owner_repo(component: str, field_name: str) -> str:
    """Validate GitHub repository owner or name component."""
    cleaned = str(component).strip()
    if not OWNER_REPO_PATTERN.fullmatch(cleaned):
        raise ProvenanceVerificationError(f"Invalid {field_name} '{component}': must match [A-Za-z0-9_.-]+")
    if ".." in cleaned:
        raise ProvenanceVerificationError(f"Invalid {field_name} '{component}': cannot contain '..'")
    return cleaned


def validate_run_url(run_url: str, run_id: int, owner: str = DEFAULT_OWNER, repo: str = DEFAULT_REPO) -> None:
    """Strictly validate run_url matching https://github.com/{owner}/{repo}/actions/runs/{run_id}."""
    valid_owner = validate_owner_repo(owner, "repository owner")
    valid_repo = validate_owner_repo(repo, "repository name")
    expected_url = f"https://github.com/{valid_owner}/{valid_repo}/actions/runs/{run_id}"
    if run_url != expected_url:
        raise ProvenanceVerificationError(
            f"Strict run_url mismatch: got '{run_url}', expected exactly '{expected_url}'"
        )


def validate_expiration(created_at_dt: datetime, mode: str, max_age_days: int) -> None:
    """Validate artifact expiration policy according to verification mode."""
    if mode == MODE_VERIFY_COMMITTED:
        return
    if mode != MODE_DOWNLOAD_FRESH:
        raise ProvenanceVerificationError(f"Invalid verification mode '{mode}': must be one of {sorted(VALID_MODES)}")
    now_utc = datetime.now(timezone.utc)
    skew_limit = now_utc + timedelta(seconds=CLOCK_SKEW_TOLERANCE_SECONDS)
    if created_at_dt > skew_limit:
        raise ProvenanceVerificationError(
            f"created_at timestamp '{created_at_dt.isoformat()}' is in the future beyond {CLOCK_SKEW_TOLERANCE_SECONDS}s skew tolerance"
        )
    age = now_utc - created_at_dt
    if age > timedelta(days=max_age_days):
        raise ProvenanceVerificationError(
            f"Artifact expired: created_at '{created_at_dt.isoformat()}' is older than {max_age_days} days"
        )


def validate_receipt_structure(receipt: dict[str, Any]) -> None:
    """Ensure all required fields exist, are non-empty and well-formed."""
    if not isinstance(receipt, dict):
        raise ProvenanceVerificationError("Receipt content must be a JSON object")
    missing_or_empty: list[str] = [
        field for field in REQUIRED_RECEIPT_FIELDS
        if field not in receipt or receipt[field] is None or (isinstance(receipt[field], str) and receipt[field].strip() == "")
    ]
    if missing_or_empty:
        raise ProvenanceVerificationError(
            f"Receipt missing or empty required fields: {sorted(missing_or_empty)}"
        )
    validate_strict_int_id(receipt["workflow_id"], "receipt workflow_id")
    validate_strict_int_id(receipt["run_id"], "receipt run_id")
    validate_strict_int_id(receipt["artifact_id"], "receipt artifact_id")
    validate_sha256_digest(receipt["archive_sha256"], "receipt archive_sha256")
    validate_sha256_digest(receipt["artifact_file_sha256"], "receipt artifact_file_sha256")


def validate_receipt_identity(
    receipt: dict[str, Any],
    policy: VerificationPolicy,
) -> None:
    """Validate exact identity constraints in the receipt against verification policy."""
    expected_norm_sha = validate_commit_sha(policy.expected_sha, field_name="expected_sha")
    receipt_sha = validate_commit_sha(receipt["commit_sha"], field_name="receipt commit_sha")
    if receipt_sha != expected_norm_sha:
        raise ProvenanceVerificationError(
            f"Commit SHA mismatch: receipt has '{receipt_sha}', expected '{expected_norm_sha}'"
        )

    receipt_branch = str(receipt["head_branch"]).strip()
    if receipt_branch != policy.expected_branch:
        raise ProvenanceVerificationError(
            f"Head branch mismatch: receipt artifact came from '{receipt_branch}', expected '{policy.expected_branch}'"
        )

    receipt_wf_name = str(receipt["workflow_name"]).strip()
    if receipt_wf_name != policy.expected_workflow_name:
        raise ProvenanceVerificationError(
            f"Workflow name mismatch: receipt has '{receipt_wf_name}', expected '{policy.expected_workflow_name}'"
        )

    receipt_wf_path = str(receipt["workflow_path"]).strip()
    if receipt_wf_path != policy.expected_workflow_path:
        raise ProvenanceVerificationError(
            f"Workflow path mismatch: receipt has '{receipt_wf_path}', expected '{policy.expected_workflow_path}'"
        )

    receipt_art_name = str(receipt["artifact_name"]).strip()
    if receipt_art_name == "latest":
        raise ProvenanceVerificationError("Artifact name cannot be 'latest'")

    expected_artifact_name = policy.expected_artifact_name
    if expected_artifact_name is None:
        expected_artifact_name = f"openapi-{expected_norm_sha}"
    if receipt_art_name != expected_artifact_name:
        raise ProvenanceVerificationError(
            f"Artifact name mismatch: receipt has '{receipt_art_name}', expected '{expected_artifact_name}'"
        )

    receipt_event = str(receipt["event"]).strip()
    if receipt_event not in policy.allowed_events:
        raise ProvenanceVerificationError(
            f"Event '{receipt_event}' not in allowed workflow events: {sorted(policy.allowed_events)}"
        )

    if policy.expected_workflow_id is not None:
        validate_strict_int_id(policy.expected_workflow_id, "expected_workflow_id")
        if receipt["workflow_id"] != policy.expected_workflow_id:
            raise ProvenanceVerificationError(
                f"Workflow ID mismatch: receipt has {receipt['workflow_id']}, expected {policy.expected_workflow_id}"
            )


def verify_artifact_file(artifact_path: Path, expected_digest: str) -> str:
    """Independently hash artifact file bounded by MAX_ARTIFACT_BYTES and compare."""
    read_bounded_bytes(artifact_path, MAX_ARTIFACT_BYTES, "Artifact")
    computed_digest = compute_file_sha256(artifact_path)
    expected_norm = expected_digest.strip().lower()
    if computed_digest != expected_norm:
        raise ProvenanceVerificationError(
            f"Artifact file digest mismatch: computed SHA256 '{computed_digest}' does not match receipt digest '{expected_norm}'"
        )
    return computed_digest


def verify_provenance(
    artifact_path: str | Path,
    receipt_path: str | Path,
    policy: VerificationPolicy,
    mode: str = MODE_VERIFY_COMMITTED,
    max_age_days: int = DEFAULT_MAX_AGE_DAYS,
) -> dict[str, Any]:
    """Execute complete fail-closed provenance verification."""
    art_path = Path(artifact_path)
    rec_path = Path(receipt_path)

    receipt_bytes = read_bounded_bytes(rec_path, MAX_RECEIPT_BYTES, "Receipt")
    try:
        receipt_data = json.loads(receipt_bytes.decode("utf-8"), object_pairs_hook=check_duplicate_keys)
    except json.JSONDecodeError as exc:
        raise ProvenanceVerificationError(f"Receipt file is malformed JSON: {exc}") from exc

    validate_receipt_structure(receipt_data)

    norm_expected_sha = validate_commit_sha(policy.expected_sha, "expected_sha")
    art_name = policy.expected_artifact_name
    if art_name is None:
        art_name = f"openapi-{norm_expected_sha}"

    effective_policy = replace(
        policy,
        expected_sha=norm_expected_sha,
        expected_artifact_name=art_name,
    )

    validate_receipt_identity(
        receipt=receipt_data,
        policy=effective_policy,
    )

    validate_run_url(
        run_url=receipt_data["run_url"],
        run_id=receipt_data["run_id"],
        owner=effective_policy.owner,
        repo=effective_policy.repo,
    )

    created_at_dt = parse_utc_timestamp(str(receipt_data["created_at"]))
    validate_expiration(created_at_dt=created_at_dt, mode=mode, max_age_days=max_age_days)

    computed_file_digest = verify_artifact_file(
        artifact_path=art_path,
        expected_digest=receipt_data["artifact_file_sha256"],
    )

    return {
        "status": "verified",
        "commit_sha": receipt_data["commit_sha"],
        "head_branch": receipt_data["head_branch"],
        "workflow_name": receipt_data["workflow_name"],
        "workflow_path": receipt_data["workflow_path"],
        "workflow_id": receipt_data["workflow_id"],
        "artifact_name": receipt_data["artifact_name"],
        "event": receipt_data["event"],
        "archive_sha256": receipt_data["archive_sha256"],
        "artifact_file_sha256": computed_file_digest,
        "run_id": receipt_data["run_id"],
        "artifact_id": receipt_data["artifact_id"],
        "mode": mode,
    }


__all__ = [
    "CLOCK_SKEW_TOLERANCE_SECONDS",
    "DEFAULT_ALLOWED_EVENTS",
    "DEFAULT_BRANCH",
    "DEFAULT_MAX_AGE_DAYS",
    "DEFAULT_OWNER",
    "DEFAULT_REPO",
    "DEFAULT_WORKFLOW_NAME",
    "DEFAULT_WORKFLOW_PATH",
    "MAX_ARTIFACT_BYTES",
    "MAX_RECEIPT_BYTES",
    "MODE_DOWNLOAD_FRESH",
    "MODE_VERIFY_COMMITTED",
    "OWNER_REPO_PATTERN",
    "REQUIRED_RECEIPT_FIELDS",
    "SHA256_PATTERN",
    "SHA40_PATTERN",
    "VALID_MODES",
    "ProvenanceVerificationError",
    "VerificationPolicy",
    "check_duplicate_keys",
    "compute_file_sha256",
    "parse_utc_timestamp",
    "read_bounded_bytes",
    "validate_commit_sha",
    "validate_expiration",
    "validate_owner_repo",
    "validate_receipt_identity",
    "validate_receipt_structure",
    "validate_run_url",
    "validate_sha256_digest",
    "validate_strict_int_id",
    "verify_artifact_file",
    "verify_provenance",
]
