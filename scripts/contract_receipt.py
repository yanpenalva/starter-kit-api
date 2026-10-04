"""Receipt construction helper for OpenAPI contract provenance.

Responsibility:
Constructs and validates the producer-side provenance receipt dictionary
containing GitHub Actions workflow run and artifact metadata.
Depends only on the foundation contract_core module.
"""

from __future__ import annotations

from dataclasses import dataclass
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


@dataclass(frozen=True)
class ReceiptRecord:
    """Typed data record representing all 13 fields of a contract provenance receipt."""

    workflow_name: str
    workflow_path: str
    workflow_id: int
    run_id: int
    run_url: str
    artifact_id: int
    artifact_name: str
    commit_sha: str
    head_branch: str
    event: str
    created_at: str
    archive_sha256: str
    artifact_file_sha256: str


def build_receipt(record: ReceiptRecord) -> dict[str, Any]:
    """Construct a validated provenance receipt dictionary from a ReceiptRecord."""
    if not isinstance(record, ReceiptRecord):
        raise ProvenanceVerificationError("record must be an instance of ReceiptRecord")
    receipt = {
        "workflow_name": str(record.workflow_name).strip(),
        "workflow_path": str(record.workflow_path).strip(),
        "workflow_id": validate_strict_int_id(record.workflow_id, "workflow_id"),
        "run_id": validate_strict_int_id(record.run_id, "run_id"),
        "run_url": str(record.run_url).strip(),
        "artifact_id": validate_strict_int_id(record.artifact_id, "artifact_id"),
        "artifact_name": str(record.artifact_name).strip(),
        "commit_sha": validate_commit_sha(record.commit_sha),
        "head_branch": str(record.head_branch).strip(),
        "event": str(record.event).strip(),
        "created_at": str(record.created_at).strip(),
        "archive_sha256": validate_sha256_digest(record.archive_sha256, "archive_sha256"),
        "artifact_file_sha256": validate_sha256_digest(record.artifact_file_sha256, "artifact_file_sha256"),
    }
    validate_receipt_structure(receipt)
    return receipt


__all__ = ["ReceiptRecord", "build_receipt"]
