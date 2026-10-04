"""Artifact provenance verification for OpenAPI contract delivery.

This script enforces fail-closed provenance validation for OpenAPI contract artifacts
produced by GitHub Actions workflows in the API repository (producer side).

Security and transport design:
- Validates that the artifact originated from the 'main' branch at an EXACT pinned 40-character commit SHA.
- Rejects non-pinned references such as 'latest', branch names, or short SHAs.
- Enforces an allowlist of trusted triggering events (e.g. workflow_dispatch, release, workflow_run).
- Independently recomputes the SHA256 digest of the artifact file and verifies it against the receipt.
- Validates receipt metadata completeness (workflow name/id, run id/url, artifact id/name, commit SHA, created_at).
- Fails closed on any missing, malformed, divergent, or unallowlisted value.

Consumer transport context (Frontend repository):
- The consumer commits the OpenAPI spec, generated TypeScript types, and provenance receipt directly
  to its repository without requiring cross-repository CI credentials.
  Default GITHUB_TOKEN (contents:read) does not cross repositories, and fork builds must remain token-free.
  Synchronization is orchestrated locally using the authorized 'gh' CLI.
- This verification authenticates GitHub Actions artifact metadata; it does NOT implement cryptographic
  attestation or signature verification (no notary/cosign signing is claimed or required in Stage A).
- In Stage B, the export pipeline must run deterministically under LC_ALL=C and verify 2x idempotent export.
"""

from __future__ import annotations

import argparse
from datetime import datetime
import hashlib
import json
from pathlib import Path
import re
import sys
from typing import Any

DEFAULT_BRANCH: str = "main"
DEFAULT_ALLOWED_EVENTS: frozenset[str] = frozenset({"workflow_dispatch", "release", "workflow_run"})
SHA40_PATTERN: re.Pattern[str] = re.compile(r"^[0-9a-fA-F]{40}$")
SHA256_PATTERN: re.Pattern[str] = re.compile(r"^[0-9a-fA-F]{64}$")

REQUIRED_RECEIPT_FIELDS: tuple[str, ...] = (
    "workflow_name",
    "workflow_id",
    "run_id",
    "run_url",
    "artifact_id",
    "artifact_name",
    "commit_sha",
    "head_branch",
    "event",
    "created_at",
    "artifact_sha256",
)


class ProvenanceVerificationError(Exception):
    """Raised when artifact provenance verification fails closed."""


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


def validate_sha256_digest(digest: str, field_name: str = "artifact_sha256") -> str:
    """Validate that a digest is an exact 64-character hexadecimal string."""
    cleaned = str(digest).strip().lower()
    if not SHA256_PATTERN.fullmatch(cleaned):
        raise ProvenanceVerificationError(
            f"Invalid {field_name} '{digest}': must be exact 64-character hexadecimal SHA256 digest"
        )
    return cleaned


def validate_timestamp_format(timestamp_str: str) -> None:
    """Validate ISO 8601 timestamp string."""
    cleaned = str(timestamp_str).strip()
    if not cleaned:
        raise ProvenanceVerificationError("Receipt created_at timestamp cannot be empty")
    try:
        normalized = cleaned.replace("Z", "+00:00")
        datetime.fromisoformat(normalized)
    except (ValueError, TypeError) as exc:
        raise ProvenanceVerificationError(
            f"Invalid ISO 8601 created_at timestamp '{timestamp_str}': {exc}"
        ) from exc


def validate_positive_id(value: Any, field_name: str) -> int:
    """Validate that an ID is a positive integer."""
    try:
        integer_val = int(value)
        if integer_val <= 0:
            raise ProvenanceVerificationError(f"{field_name} must be a positive integer, got '{value}'")
        return integer_val
    except (ValueError, TypeError) as exc:
        raise ProvenanceVerificationError(f"{field_name} must be a valid integer, got '{value}'") from exc


def validate_receipt_structure(receipt: dict[str, Any]) -> None:
    """Ensure all required fields exist and are non-empty."""
    if not isinstance(receipt, dict):
        raise ProvenanceVerificationError("Receipt content must be a JSON object")
    missing_or_empty: list[str] = [
        field for field in REQUIRED_RECEIPT_FIELDS
        if field not in receipt or receipt[field] is None or str(receipt[field]).strip() == ""
    ]
    if missing_or_empty:
        raise ProvenanceVerificationError(
            f"Receipt missing or empty required fields: {sorted(missing_or_empty)}"
        )


def validate_receipt_content(
    receipt: dict[str, Any],
    expected_sha: str,
    expected_branch: str,
    allowed_events: frozenset[str],
) -> None:
    """Validate metadata constraints in the receipt."""
    expected_norm_sha = validate_commit_sha(expected_sha, field_name="expected_sha")
    receipt_sha = validate_commit_sha(receipt["commit_sha"], field_name="receipt commit_sha")

    if receipt_sha != expected_norm_sha:
        raise ProvenanceVerificationError(
            f"Commit SHA mismatch: receipt has '{receipt_sha}', expected '{expected_norm_sha}'"
        )

    receipt_branch = str(receipt["head_branch"]).strip()
    if receipt_branch != expected_branch:
        raise ProvenanceVerificationError(
            f"Head branch mismatch: receipt artifact came from '{receipt_branch}', expected '{expected_branch}'"
        )

    receipt_event = str(receipt["event"]).strip()
    if receipt_event not in allowed_events:
        raise ProvenanceVerificationError(
            f"Event '{receipt_event}' not in allowed workflow events: {sorted(allowed_events)}"
        )

    validate_sha256_digest(receipt["artifact_sha256"], field_name="receipt artifact_sha256")
    validate_timestamp_format(str(receipt["created_at"]))
    validate_positive_id(receipt["run_id"], "run_id")
    validate_positive_id(receipt["artifact_id"], "artifact_id")

    run_url = str(receipt["run_url"]).strip()
    if not (run_url.startswith("https://") or run_url.startswith("http://")):
        raise ProvenanceVerificationError(f"Invalid run_url '{run_url}': must be a valid URL")


def verify_artifact_file(artifact_path: Path, expected_digest: str) -> str:
    """Independently hash artifact file and compare against expected digest."""
    if not artifact_path.is_file():
        raise ProvenanceVerificationError(f"Artifact file not found: {artifact_path}")
    computed_digest = compute_file_sha256(artifact_path)
    expected_norm = expected_digest.strip().lower()
    if computed_digest != expected_norm:
        raise ProvenanceVerificationError(
            f"Artifact digest mismatch: computed SHA256 '{computed_digest}' does not match receipt digest '{expected_norm}'"
        )
    return computed_digest


def verify_provenance(
    artifact_path: Path,
    receipt_path: Path,
    expected_sha: str,
    expected_branch: str = DEFAULT_BRANCH,
    allowed_events: frozenset[str] = DEFAULT_ALLOWED_EVENTS,
) -> dict[str, Any]:
    """Execute complete fail-closed provenance verification."""
    if not receipt_path.is_file():
        raise ProvenanceVerificationError(f"Receipt file not found: {receipt_path}")

    try:
        receipt_data = json.loads(receipt_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise ProvenanceVerificationError(f"Receipt file is malformed JSON: {exc}") from exc

    validate_receipt_structure(receipt_data)
    validate_receipt_content(receipt_data, expected_sha, expected_branch, allowed_events)
    computed_digest = verify_artifact_file(artifact_path, receipt_data["artifact_sha256"])

    return {
        "status": "verified",
        "commit_sha": receipt_data["commit_sha"],
        "head_branch": receipt_data["head_branch"],
        "event": receipt_data["event"],
        "artifact_sha256": computed_digest,
        "run_id": receipt_data["run_id"],
        "artifact_id": receipt_data["artifact_id"],
    }


def build_receipt(
    workflow_name: str,
    workflow_id: int | str,
    run_id: int,
    run_url: str,
    artifact_id: int,
    artifact_name: str,
    commit_sha: str,
    head_branch: str,
    event: str,
    created_at: str,
    artifact_sha256: str,
) -> dict[str, Any]:
    """Helper to construct a validated provenance receipt dictionary."""
    receipt = {
        "workflow_name": str(workflow_name).strip(),
        "workflow_id": workflow_id,
        "run_id": validate_positive_id(run_id, "run_id"),
        "run_url": str(run_url).strip(),
        "artifact_id": validate_positive_id(artifact_id, "artifact_id"),
        "artifact_name": str(artifact_name).strip(),
        "commit_sha": validate_commit_sha(commit_sha),
        "head_branch": str(head_branch).strip(),
        "event": str(event).strip(),
        "created_at": str(created_at).strip(),
        "artifact_sha256": validate_sha256_digest(artifact_sha256),
    }
    validate_receipt_structure(receipt)
    return receipt


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Verify fail-closed OpenAPI contract artifact provenance."
    )
    parser.add_argument(
        "--artifact-file",
        required=True,
        type=Path,
        help="Path to the OpenAPI contract artifact file",
    )
    parser.add_argument(
        "--receipt-file",
        required=True,
        type=Path,
        help="Path to the provenance receipt JSON file",
    )
    parser.add_argument(
        "--expected-sha",
        required=True,
        type=str,
        help="Exact pinned 40-character commit SHA (from main)",
    )
    parser.add_argument(
        "--expected-branch",
        default=DEFAULT_BRANCH,
        type=str,
        help=f"Expected source branch (default: {DEFAULT_BRANCH})",
    )
    parser.add_argument(
        "--allowed-events",
        default=",".join(sorted(DEFAULT_ALLOWED_EVENTS)),
        type=str,
        help="Comma-separated allowlist of triggering workflow events",
    )
    return parser.parse_args(argv)


def run_cli(argv: list[str]) -> int:
    try:
        args = parse_args(argv)
        allowed_events = frozenset(
            event.strip() for event in args.allowed_events.split(",") if event.strip()
        )
        if not allowed_events:
            raise ProvenanceVerificationError("Allowed events allowlist cannot be empty")

        result = verify_provenance(
            artifact_path=args.artifact_file,
            receipt_path=args.receipt_file,
            expected_sha=args.expected_sha,
            expected_branch=args.expected_branch,
            allowed_events=allowed_events,
        )
        print(f"SUCCESS: Artifact provenance verified for commit {result['commit_sha']} (sha256: {result['artifact_sha256']})")
        return 0
    except ProvenanceVerificationError as exc:
        print(f"FAIL CLOSED: Provenance verification failed: {exc}", file=sys.stderr)
        return 1
    except Exception as exc:
        print(f"FAIL CLOSED: Unexpected error: {exc}", file=sys.stderr)
        return 2


def main() -> None:
    sys.exit(run_cli(sys.argv[1:]))


if __name__ == "__main__":
    main()
