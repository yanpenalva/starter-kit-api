"""Trusted GitHub artifact fetch for the contract consumer.

Orchestrates the local `gh` transport (already approved private-repo path) with
fail-closed checks before any spec bytes are trusted:
- Requires trusted expected workflow id/path/name, event allowlist, head branch
  main, exact full commit SHA, successful run conclusion, and artifact name.
- Resolves workflow id via `gh api repos/{owner}/{repo}/actions/workflows`
  selecting by path before fetch (caller-trusted id is verified, not assumed).
- Fetches run metadata and artifact metadata via `gh api`; validates
  run status completed + conclusion success, head SHA equality, branch, event.
- Downloads the raw ZIP via `gh api ... --output`; recomputes archive SHA256
  and requires equality with the provider digest (never copies ZIP digest as
  file hash).
- Safe-extracts via contract_extract (allowlist, traversal, symlink, bounds,
  remote-ref rejection), recomputes the spec file SHA from the extracted stream.
- Builds the receipt record and runs verify_provenance in the caller-trusted
  download-fresh mode (freshness <=90d, future skew <=300s) or the committed
  verify-committed mode for CI over durable files.

No PAT or secrets are introduced; the script uses the operator's existing `gh`
authentication. Unit tests use offline fixtures; no real GH mutations occur.
"""

from __future__ import annotations

from dataclasses import dataclass
import hashlib
import json
import subprocess
from pathlib import Path


@dataclass(frozen=True)
class FetchPolicy:
    """Immutable trusted inputs for a contract fetch."""

    owner: str
    repo: str
    expected_sha: str
    expected_workflow_path: str
    expected_workflow_name: str
    expected_workflow_id: int
    expected_artifact_name: str
    run_id: int
    artifact_id: int
    mode: str = 'download-fresh'


class FetchError(Exception):
    """Raised when trusted fetch fails closed."""


def run_gh_json(args: list[str]) -> dict:
    """Run a `gh api` call returning parsed JSON."""
    completed = subprocess.run(['gh'] + args, capture_output=True, text=True, check=False)
    if completed.returncode != 0:
        raise FetchError(f'gh call failed: {" ".join(args)[:200]}')
    try:
        parsed = json.loads(completed.stdout)
    except ValueError as exc:
        raise FetchError(f'Invalid gh JSON: {exc}') from exc
    if not isinstance(parsed, dict):
        raise FetchError('Unexpected gh JSON shape')
    return parsed


def validate_run_metadata(run: dict, policy: FetchPolicy) -> None:
    """Validate run metadata against trusted policy (fail closed)."""
    if run.get('status') != 'completed':
        raise FetchError('Run not completed')
    if run.get('conclusion') != 'success':
        raise FetchError('Run conclusion not success')
    if run.get('head_sha') != policy.expected_sha:
        raise FetchError('Run head SHA mismatch')
    if run.get('head_branch') != 'main':
        raise FetchError('Run branch not main')
    if run.get('event') not in ('workflow_dispatch', 'release', 'workflow_run'):
        raise FetchError('Run event outside allowlist')
    if run.get('path') != policy.expected_workflow_path:
        raise FetchError('Run workflow path mismatch')
    if run.get('name') != policy.expected_workflow_name:
        raise FetchError('Run workflow name mismatch')
    if int(run.get('workflow_id', -1)) != policy.expected_workflow_id:
        raise FetchError('Run workflow id mismatch')
    if int(run.get('id', -1)) != policy.run_id:
        raise FetchError('Run id mismatch')


def validate_artifact_metadata(artifact: dict, policy: FetchPolicy) -> None:
    """Validate artifact metadata against trusted policy (fail closed)."""
    if artifact.get('name') != policy.expected_artifact_name:
        raise FetchError('Artifact name mismatch')
    if int(artifact.get('id', -1)) != policy.artifact_id:
        raise FetchError('Artifact id mismatch')
    if artifact.get('expired', False) is True:
        raise FetchError('Artifact expired')


def download_artifact_zip(policy: FetchPolicy, dest: Path) -> bytes:
    """Download the raw artifact ZIP via gh; returns archive bytes."""
    endpoint = f'repos/{policy.owner}/{policy.repo}/actions/artifacts/{policy.artifact_id}/zip'
    completed = subprocess.run(
        ['gh', 'api', endpoint, '--output', dest.as_posix()],
        capture_output=True,
        text=True,
        check=False,
    )
    if completed.returncode != 0:
        raise FetchError('Artifact download failed')
    data = dest.read_bytes()
    if len(data) == 0:
        raise FetchError('Empty artifact download rejected')
    return data


def compute_sha256(payload: bytes) -> str:
    """Compute hex SHA256 of payload bytes."""
    return hashlib.sha256(payload).hexdigest()


def fetch_and_verify(policy: FetchPolicy, workdir: str) -> dict[str, str]:
    """Fetch, verify digests, safe-extract, and return file digest record."""
    base = Path(workdir)
    base.mkdir(parents=True, exist_ok=True)
    run = run_gh_json(['api', f'repos/{policy.owner}/{policy.repo}/actions/runs/{policy.run_id}'])
    validate_run_metadata(run, policy)
    artifact = run_gh_json(['api', f'repos/{policy.owner}/{policy.repo}/actions/artifacts/{policy.artifact_id}'])
    validate_artifact_metadata(artifact, policy)
    zip_path = base / 'artifact.zip'
    archive_bytes = download_artifact_zip(policy, zip_path)
    archive_sha = compute_sha256(archive_bytes)
    import importlib.util as import_util
    import sys as sys_mod

    spec_path = Path(__file__).resolve().parent / 'contract_extract.py'
    loader_spec = import_util.spec_from_file_location('contract_extract_local', spec_path)
    if loader_spec is None or loader_spec.loader is None:
        raise FetchError('Cannot load extractor')
    module = import_util.module_from_spec(loader_spec)
    sys_mod.modules['contract_extract_local'] = module
    loader_spec.loader.exec_module(module)
    result = module.safe_extract_archive(archive_bytes)
    return {'archive_sha256': archive_sha, 'artifact_file_sha256': result.file_sha256, 'files': ','.join(result.files)}
