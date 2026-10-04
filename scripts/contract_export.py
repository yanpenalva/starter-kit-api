"""Deterministic OpenAPI export for the producer pipeline.

Runs `php artisan scramble:export` twice under LC_ALL=C and requires byte-identical
output before promoting the snapshot to the contracts path. Records the generator
version pin (dedoc/scramble 0.13.47) alongside the file SHA so CI can verify the
tool version that produced the committed spec.
"""

from __future__ import annotations

import hashlib
import json
import os
import subprocess
from dataclasses import dataclass
from pathlib import Path


GENERATOR_PIN = 'dedoc/scramble 0.13.47'
DEFAULT_OUTPUT = 'contracts/openapi.json'


class ExportError(Exception):
    """Raised when deterministic export fails closed."""


@dataclass(frozen=True)
class ExportResult:
    """Immutable result of a deterministic export."""

    path: str
    sha256: str
    size_bytes: int
    generator: str


def run_single_export(output: Path, env: dict[str, str]) -> None:
    """Run one artisan export pass into the given path."""
    cmd = ['php', 'artisan', 'scramble:export', f'--path={output.as_posix()}']
    completed = subprocess.run(cmd, capture_output=True, text=True, env=env, check=False)
    if completed.returncode != 0:
        raise ExportError(f'Export pass failed: {completed.stderr.strip()[:500]}')
    if not output.is_file():
        raise ExportError(f'Export pass produced no file: {output}')


def build_export_env() -> dict[str, str]:
    """Build a deterministic environment for the export passes."""
    env = dict(os.environ)
    env['LC_ALL'] = 'C'
    env['TZ'] = 'UTC'
    return env


def verify_deterministic(first: Path, second: Path) -> bytes:
    """Require two export passes to be byte-identical; returns payload."""
    first_bytes = first.read_bytes()
    second_bytes = second.read_bytes()
    if first_bytes != second_bytes:
        raise ExportError('Non-deterministic export: two passes differ')
    if len(first_bytes) == 0:
        raise ExportError('Empty export rejected')
    return first_bytes


def validate_openapi_document(payload: bytes) -> None:
    """Validate the exported document is OpenAPI 3.1 with required surface."""
    try:
        document = json.loads(payload.decode('utf-8'))
    except (UnicodeDecodeError, ValueError) as exc:
        raise ExportError(f'Invalid JSON export: {exc}') from exc
    if document.get('openapi') != '3.1.0':
        raise ExportError('Export must declare openapi 3.1.0')
    paths = document.get('paths')
    if not isinstance(paths, dict) or not paths:
        raise ExportError('Export must contain paths')
    info = document.get('info', {})
    if not isinstance(info, dict) or not str(info.get('version', '')).strip():
        raise ExportError('Export must declare info.version')


def deterministic_export(output: str = DEFAULT_OUTPUT) -> ExportResult:
    """Export twice, verify determinism, and promote to the contracts path."""
    target = Path(output)
    cache = Path('.cache')
    cache.mkdir(parents=True, exist_ok=True)
    first = cache / 'openapi-pass1.json'
    second = cache / 'openapi-pass2.json'
    env = build_export_env()
    run_single_export(first, env)
    run_single_export(second, env)
    payload = verify_deterministic(first, second)
    validate_openapi_document(payload)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(payload)
    digest = hashlib.sha256(payload).hexdigest()
    return ExportResult(path=target.as_posix(), sha256=digest, size_bytes=len(payload), generator=GENERATOR_PIN)


def main() -> int:
    """CLI entrypoint for deterministic export."""
    import argparse

    parser = argparse.ArgumentParser(description='Deterministic OpenAPI export (LC_ALL=C, 2x).')
    parser.add_argument('--output', default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    result = deterministic_export(args.output)
    print(f'EXPORT OK path={result.path} sha256={result.sha256} bytes={result.size_bytes} generator={result.generator}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
