"""Artifact provenance verification for OpenAPI contract delivery.

This script enforces fail-closed provenance validation for OpenAPI contract artifacts
produced by GitHub Actions workflows in the API repository (producer side).

Security and transport design:
- Validates that the artifact originated from the 'main' branch at an EXACT pinned 40-character commit SHA.
- Rejects non-pinned references such as 'latest', branch names, or short SHAs.
- Enforces an allowlist of trusted triggering events (e.g. workflow_dispatch, release, workflow_run).
- Enforces strict equality for workflow_name, workflow_path, and artifact_name.
- Validates that workflow_id is an integer associated with the trusted workflow path/name.
  Contract: in production orchestration, workflow_id is resolved prior to fetch via:
    gh api repos/{owner}/{repo}/actions/workflows --jq '.workflows[] | select(.path=="...") | .id'
  and supplied as expected_workflow_id to prevent accepting receipts from unrelated workflows.
- Strictly validates run_url: https://github.com/{owner}/{repo}/actions/runs/{run_id} without query/fragment,
  matching the receipt run_id (anti-confused-deputy).
- Rejects duplicate JSON keys in receipt parsing (object_pairs_hook).
- Enforces strict integer types for IDs (rejecting bool, float, string numeric).
- Enforces nominal size boundaries (MAX_RECEIPT_BYTES = 64 KiB, MAX_ARTIFACT_BYTES = 10 MiB)
  before parsing or hashing to avoid memory exhaustion denial.
- Distinguishes archive_sha256 (provider ZIP digest) from artifact_file_sha256 (extracted spec file hash).
  The spec file hash is independently recomputed from the artifact file stream.
- Supports two explicit verification modes:
  (a) download-fresh: enforces max_age (default 90 days) against reliable UTC clock; future created_at
      beyond 300s skew tolerance fails closed.
  (b) verify-committed: verifies schema, identity, and digests without age check
      (durable committed spec/receipt in consumer repository does not expire randomly on consumer CI).
  Fetch-time enforcement: missing or expired artifacts fail at first fetch before commit.

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

import importlib.util
from pathlib import Path
import sys
from typing import Any


def _load_sibling(name: str) -> Any:
    cached = sys.modules.get(name) or sys.modules.get(f"scripts.{name}")
    if cached is not None:
        return cached
    if __package__:
        return importlib.import_module(f"{__package__}.{name}")
    path = Path(__file__).resolve().parent / f"{name}.py"
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise ImportError(f"Cannot load sibling module '{name}' from '{path}'")
    mod = importlib.util.module_from_spec(spec)
    sys.modules[name] = mod
    spec.loader.exec_module(mod)
    return mod


_core = _load_sibling("contract_core")
_receipt = _load_sibling("contract_receipt")
_cli = _load_sibling("contract_cli")

# Re-exported constants from contract_core
CLOCK_SKEW_TOLERANCE_SECONDS = _core.CLOCK_SKEW_TOLERANCE_SECONDS
DEFAULT_ALLOWED_EVENTS = _core.DEFAULT_ALLOWED_EVENTS
DEFAULT_BRANCH = _core.DEFAULT_BRANCH
DEFAULT_MAX_AGE_DAYS = _core.DEFAULT_MAX_AGE_DAYS
DEFAULT_OWNER = _core.DEFAULT_OWNER
DEFAULT_REPO = _core.DEFAULT_REPO
DEFAULT_WORKFLOW_NAME = _core.DEFAULT_WORKFLOW_NAME
DEFAULT_WORKFLOW_PATH = _core.DEFAULT_WORKFLOW_PATH
MAX_ARTIFACT_BYTES = _core.MAX_ARTIFACT_BYTES
MAX_RECEIPT_BYTES = _core.MAX_RECEIPT_BYTES
MODE_DOWNLOAD_FRESH = _core.MODE_DOWNLOAD_FRESH
MODE_VERIFY_COMMITTED = _core.MODE_VERIFY_COMMITTED
OWNER_REPO_PATTERN = _core.OWNER_REPO_PATTERN
REQUIRED_RECEIPT_FIELDS = _core.REQUIRED_RECEIPT_FIELDS
SHA256_PATTERN = _core.SHA256_PATTERN
SHA40_PATTERN = _core.SHA40_PATTERN
VALID_MODES = _core.VALID_MODES

# Re-exported classes
ProvenanceVerificationError = _core.ProvenanceVerificationError
VerificationPolicy = _core.VerificationPolicy

# Re-exported functions from contract_core
check_duplicate_keys = _core.check_duplicate_keys
compute_file_sha256 = _core.compute_file_sha256
parse_utc_timestamp = _core.parse_utc_timestamp
read_bounded_bytes = _core.read_bounded_bytes
validate_commit_sha = _core.validate_commit_sha
validate_expiration = _core.validate_expiration
validate_owner_repo = _core.validate_owner_repo
validate_receipt_identity = _core.validate_receipt_identity
validate_receipt_structure = _core.validate_receipt_structure
validate_run_url = _core.validate_run_url
validate_sha256_digest = _core.validate_sha256_digest
validate_strict_int_id = _core.validate_strict_int_id
verify_artifact_file = _core.verify_artifact_file
verify_provenance = _core.verify_provenance

# Re-exported from contract_receipt
ReceiptRecord = _receipt.ReceiptRecord
build_receipt = _receipt.build_receipt

# Re-exported from contract_cli
parse_args = _cli.parse_args
run_cli = _cli.run_cli
main = _cli.main


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
    "ReceiptRecord",
    "VerificationPolicy",
    "build_receipt",
    "check_duplicate_keys",
    "compute_file_sha256",
    "main",
    "parse_args",
    "parse_utc_timestamp",
    "read_bounded_bytes",
    "run_cli",
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


if __name__ == "__main__":
    main()
