"""Receipt construction helper for OpenAPI contract provenance.

Responsibility:
Constructs and validates the producer-side provenance receipt dictionary
containing GitHub Actions workflow run and artifact metadata.
Depends only on the foundation contract_core module.
"""

from __future__ import annotations

import importlib.util
from pathlib import Path
import sys
from typing import Any


def _load_core() -> Any:
    cached = sys.modules.get("contract_core") or sys.modules.get("scripts.contract_core")
    if cached is not None:
        return cached
    if __package__:
        return importlib.import_module(f"{__package__}.contract_core")
    path = Path(__file__).resolve().parent / "contract_core.py"
    spec = importlib.util.spec_from_file_location("contract_core", path)
    if spec is None or spec.loader is None:
        raise ImportError(f"Cannot load contract_core from '{path}'")
    mod = importlib.util.module_from_spec(spec)
    sys.modules["contract_core"] = mod
    spec.loader.exec_module(mod)
    return mod


_core = _load_core()
ProvenanceVerificationError = _core.ProvenanceVerificationError
validate_commit_sha = _core.validate_commit_sha
validate_receipt_structure = _core.validate_receipt_structure
validate_sha256_digest = _core.validate_sha256_digest
validate_strict_int_id = _core.validate_strict_int_id


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
