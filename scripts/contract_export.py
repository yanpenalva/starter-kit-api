"""Exportação determinística de OpenAPI para o pipeline produtor.

Executa `php artisan scramble:export` duas vezes sob LC_ALL=C e exige saída
byte a byte idêntica antes de promover o snapshot para o caminho de contratos.
Registra o pin exato do gerador (dedoc/scramble 0.13.47) junto ao SHA do arquivo.
"""

from __future__ import annotations

import argparse
from dataclasses import dataclass
import hashlib
import json
import os
from pathlib import Path
import subprocess

GENERATOR_PIN: str = "dedoc/scramble 0.13.47"
DEFAULT_OUTPUT: str = "contracts/openapi.json"


class ExportError(Exception):
    """Lançada quando a exportação determinística falha em modo fail-closed."""


@dataclass(frozen=True)
class ExportResult:
    """Resultado imutável de uma exportação determinística."""

    path: str
    sha256: str
    size_bytes: int
    generator: str


def run_single_export(output: Path, env: dict[str, str]) -> None:
    """Executa um passo de exportação via artisan para o caminho indicado."""
    cmd = ["php", "artisan"]
    runner = os.getenv("ARTISAN_RUNNER")
    if runner:
        cmd = runner.split()
    cmd = [*cmd, "scramble:export", f"--path={output.as_posix()}"]
    completed = subprocess.run(
        cmd, capture_output=True, text=True, env=env, check=False
    )
    if completed.returncode != 0:
        raise ExportError(
            f"Falha no passo de exportação: {completed.stderr.strip()[:500]}"
        )
    if not output.is_file():
        raise ExportError(
            f"Passo de exportação não gerou o arquivo esperado: {output}"
        )


def build_export_env() -> dict[str, str]:
    """Constrói ambiente determinístico para os passos de exportação."""
    env = dict(os.environ)
    env["LC_ALL"] = "C"
    env["TZ"] = "UTC"
    return env


def verify_deterministic(first: Path, second: Path) -> bytes:
    """Exige que dois passos de exportação sejam byte a byte idênticos; retorna os bytes."""
    first_bytes = first.read_bytes()
    second_bytes = second.read_bytes()
    if first_bytes != second_bytes:
        raise ExportError(
            "Exportação não-determinística: dois passos divergiram."
        )
    if len(first_bytes) == 0:
        raise ExportError("Exportação vazia rejeitada.")
    return first_bytes


def validate_openapi_document(payload: bytes) -> None:
    """Valida se o documento exportado é OpenAPI 3.1 com a superfície requerida."""
    try:
        document = json.loads(payload.decode("utf-8"))
    except (UnicodeDecodeError, ValueError) as exc:
        raise ExportError(f"JSON exportado inválido: {exc}") from exc

    if document.get("openapi") != "3.1.2":
        raise ExportError("Exportação deve declarar openapi 3.1.2.")

    paths = document.get("paths")
    if not isinstance(paths, dict) or not paths:
        raise ExportError("Exportação deve conter objeto paths válido.")

    info = document.get("info", {})
    if not isinstance(info, dict) or not str(info.get("version", "")).strip():
        raise ExportError("Exportação deve declarar info.version.")


def deterministic_export(output: str = DEFAULT_OUTPUT) -> ExportResult:
    """Exporta duas vezes, verifica determinismo e promove para o caminho de contratos."""
    target = Path(output)
    cache = Path(".cache")
    cache.mkdir(parents=True, exist_ok=True)
    first = cache / "openapi-pass1.json"
    second = cache / "openapi-pass2.json"
    env = build_export_env()
    run_single_export(first, env)
    run_single_export(second, env)
    payload = verify_deterministic(first, second)
    validate_openapi_document(payload)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(payload)
    digest = hashlib.sha256(payload).hexdigest()
    return ExportResult(
        path=target.as_posix(),
        sha256=digest,
        size_bytes=len(payload),
        generator=GENERATOR_PIN,
    )


def main(args: list[str] | None = None) -> int:
    """Ponto de entrada CLI para exportação determinística."""
    parser = argparse.ArgumentParser(
        description="Exportação determinística de OpenAPI (LC_ALL=C, 2x)."
    )
    parser.add_argument("--output", default=DEFAULT_OUTPUT)
    parsed = parser.parse_args(args)
    result = deterministic_export(parsed.output)
    print(
        f"EXPORT OK path={result.path} sha256={result.sha256} bytes={result.size_bytes} generator={result.generator}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
