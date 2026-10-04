"""Command-line interface and orchestration for artifact provenance verification.

Responsibility:
Handles CLI argument parsing, input normalization, error formatting,
process exit code signaling, and terminal output orchestration.
"""

from __future__ import annotations

import argparse
from pathlib import Path
import sys


def parse_args(argv: list[str]) -> argparse.Namespace:
    """Parse and return command-line arguments."""
    if __package__:
        from scripts.verify_contract_artifact import (
            DEFAULT_ALLOWED_EVENTS,
            DEFAULT_BRANCH,
            DEFAULT_MAX_AGE_DAYS,
            DEFAULT_OWNER,
            DEFAULT_REPO,
            DEFAULT_WORKFLOW_NAME,
            DEFAULT_WORKFLOW_PATH,
            MODE_DOWNLOAD_FRESH,
            MODE_VERIFY_COMMITTED,
        )
    if not __package__:
        from verify_contract_artifact import (
            DEFAULT_ALLOWED_EVENTS,
            DEFAULT_BRANCH,
            DEFAULT_MAX_AGE_DAYS,
            DEFAULT_OWNER,
            DEFAULT_REPO,
            DEFAULT_WORKFLOW_NAME,
            DEFAULT_WORKFLOW_PATH,
            MODE_DOWNLOAD_FRESH,
            MODE_VERIFY_COMMITTED,
        )

    parser = argparse.ArgumentParser(
        description="Verify fail-closed OpenAPI contract artifact provenance."
    )
    parser.add_argument(
        "--artifact-file",
        required=True,
        type=Path,
        help="Path to the OpenAPI contract artifact file (extracted spec)",
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
        "--mode",
        default=MODE_VERIFY_COMMITTED,
        choices=[MODE_VERIFY_COMMITTED, MODE_DOWNLOAD_FRESH],
        help=f"Verification mode: {MODE_VERIFY_COMMITTED} (default, CI) or {MODE_DOWNLOAD_FRESH} (fetch)",
    )
    parser.add_argument(
        "--expected-branch",
        default=DEFAULT_BRANCH,
        type=str,
        help=f"Expected source branch (default: {DEFAULT_BRANCH})",
    )
    parser.add_argument(
        "--expected-workflow-name",
        default=DEFAULT_WORKFLOW_NAME,
        type=str,
        help=f"Expected workflow name (default: '{DEFAULT_WORKFLOW_NAME}')",
    )
    parser.add_argument(
        "--expected-workflow-path",
        default=DEFAULT_WORKFLOW_PATH,
        type=str,
        help=f"Expected workflow path (default: '{DEFAULT_WORKFLOW_PATH}')",
    )
    parser.add_argument(
        "--expected-workflow-id",
        default=None,
        type=int,
        help="Expected workflow ID (int, resolved via gh actions workflows prior to fetch)",
    )
    parser.add_argument(
        "--expected-artifact-name",
        default=None,
        type=str,
        help="Expected artifact name (default: 'openapi-<expected_sha>')",
    )
    parser.add_argument(
        "--allowed-events",
        default=",".join(sorted(DEFAULT_ALLOWED_EVENTS)),
        type=str,
        help="Comma-separated allowlist of triggering workflow events",
    )
    parser.add_argument(
        "--owner",
        default=DEFAULT_OWNER,
        type=str,
        help=f"Expected repository owner (default: {DEFAULT_OWNER})",
    )
    parser.add_argument(
        "--repo",
        default=DEFAULT_REPO,
        type=str,
        help=f"Expected repository name (default: {DEFAULT_REPO})",
    )
    parser.add_argument(
        "--max-age-days",
        default=DEFAULT_MAX_AGE_DAYS,
        type=int,
        help=f"Max age in days for download-fresh mode (default: {DEFAULT_MAX_AGE_DAYS})",
    )
    return parser.parse_args(argv)


def run_cli(argv: list[str]) -> int:
    """Execute CLI orchestration returning standard exit codes (0=success, 1=verify fail, 2=unexpected)."""
    if __package__:
        from scripts.verify_contract_artifact import (
            ProvenanceVerificationError,
            verify_provenance,
        )
    if not __package__:
        from verify_contract_artifact import (
            ProvenanceVerificationError,
            verify_provenance,
        )

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
            expected_workflow_name=args.expected_workflow_name,
            expected_workflow_path=args.expected_workflow_path,
            expected_artifact_name=args.expected_artifact_name,
            expected_workflow_id=args.expected_workflow_id,
            allowed_events=allowed_events,
            owner=args.owner,
            repo=args.repo,
            mode=args.mode,
            max_age_days=args.max_age_days,
        )
        print(
            f"SUCCESS: Artifact provenance verified for commit {result['commit_sha']} "
            f"(mode: {result['mode']}, file_sha256: {result['artifact_file_sha256']}, archive_sha256: {result['archive_sha256']})"
        )
        return 0
    except ProvenanceVerificationError as exc:
        print(f"FAIL CLOSED: Provenance verification failed: {exc}", file=sys.stderr)
        return 1
    except Exception as exc:
        print(f"FAIL CLOSED: Unexpected error: {exc}", file=sys.stderr)
        return 2


def main() -> None:
    """Entrypoint function for CLI execution."""
    sys.exit(run_cli(sys.argv[1:]))


if __name__ == "__main__":
    main()

__all__ = ["parse_args", "run_cli", "main"]
