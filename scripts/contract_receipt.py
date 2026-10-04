"""Receipt construction helper for OpenAPI contract provenance.

Responsibility:
Constructs and validates the producer-side provenance receipt dictionary
containing GitHub Actions workflow run and artifact metadata.
"""

from __future__ import annotations

from typing import Any


def build_receipt(
    workflow_name: str,
    workflow_path: str,
    workflow_id: int,
    run_id: int,
    run_url: str,
    artifact_id: int,
    artifact_name: str,
    commit_sha: str,
    head_branch: str,
    event: str,
    created_at: str,
    archive_sha256: str,
    artifact_file_sha256: str,
) -> dict[str, Any]:
    """Helper to construct a validated provenance receipt dictionary.

    Legacy public interface constraint: 13 parameters preserved for compatibility.
    """
    if __package__:
        from scripts.verify_contract_artifact import (
            validate_commit_sha,
            validate_receipt_structure,
            validate_sha256_digest,
            validate_strict_int_id,
        )
    if not __package__:
        from verify_contract_artifact import (
            validate_commit_sha,
            validate_receipt_structure,
            validate_sha256_digest,
            validate_strict_int_id,
        )

    receipt = {
        "workflow_name": str(workflow_name).strip(),
        "workflow_path": str(workflow_path).strip(),
        "workflow_id": validate_strict_int_id(workflow_id, "workflow_id"),
        "run_id": validate_strict_int_id(run_id, "run_id"),
        "run_url": str(run_url).strip(),
        "artifact_id": validate_strict_int_id(artifact_id, "artifact_id"),
        "artifact_name": str(artifact_name).strip(),
        "commit_sha": validate_commit_sha(commit_sha),
        "head_branch": str(head_branch).strip(),
        "event": str(event).strip(),
        "created_at": str(created_at).strip(),
        "archive_sha256": validate_sha256_digest(archive_sha256, "archive_sha256"),
        "artifact_file_sha256": validate_sha256_digest(artifact_file_sha256, "artifact_file_sha256"),
    }
    validate_receipt_structure(receipt)
    return receipt


__all__ = ["build_receipt"]
