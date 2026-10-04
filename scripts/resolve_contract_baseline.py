"""Resolvedor seguro de commit baseline para verificação de compatibilidade OpenAPI.

Extrai o SHA base imutável diretamente do payload de evento oficial do GitHub Actions:
- pull_request: base.sha (nunca head ou candidato)
- push em refs/heads/main: before (commit imediatamente anterior)

Aplica validação estrita de formato de 40 caracteres hexadecimais, rejeita zero-SHA
e o próprio HEAD atual, e garante que o commit esteja disponível no repositório git.
"""

from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys
from typing import Any

FIXED_CONTRACT_PATH: str = "contracts/openapi.json"
ALLOWED_EVENTS: tuple[str, ...] = ("pull_request", "push")
MAIN_REF: str = "refs/heads/main"
ZERO_SHA: str = "0" * 40


class BaselineResolutionError(Exception):
    """Lançada quando a resolução do commit baseline falha em modo fail-closed."""


def validate_full_sha(sha: str) -> str:
    """Valida se a string é um SHA-1 hexadecimal minúsculo estrito e não-zero de 40 caracteres."""
    normalized = str(sha).strip().lower()
    if not re.fullmatch(r"[0-9a-f]{40}", normalized):
        raise BaselineResolutionError(
            f"Commit baseline inválido: deve conter exatamente 40 caracteres hexadecimais minúsculos: '{sha}'."
        )
    if normalized == ZERO_SHA:
        raise BaselineResolutionError(
            "Commit baseline inválido: zero-SHA detectado (criação de branch ou commit inicial não possui base comparável)."
        )
    return normalized


def load_event_payload(event_path: Path) -> dict[str, Any]:
    """Lê e analisa o payload JSON do evento do GitHub."""
    if not event_path.is_file():
        raise BaselineResolutionError(
            f"Arquivo de payload do evento do GitHub não encontrado: '{event_path}'."
        )
    try:
        content = event_path.read_text(encoding="utf-8")
        data = json.loads(content)
        if not isinstance(data, dict):
            raise BaselineResolutionError("Payload do evento não é um objeto JSON válido.")
        return data
    except json.JSONDecodeError as exc:
        raise BaselineResolutionError(
            f"Falha ao decodificar JSON do evento do GitHub '{event_path}': {exc}."
        ) from exc


def extract_base_sha(event_name: str, event_data: dict[str, Any], git_ref: str | None = None) -> str:
    """Extrai e valida o base SHA a partir do payload de evento permitido."""
    if event_name not in ALLOWED_EVENTS:
        raise BaselineResolutionError(
            f"Evento do GitHub '{event_name}' não permitido. Eventos autorizados: {ALLOWED_EVENTS}."
        )

    if event_name == "pull_request":
        pr_data = event_data.get("pull_request")
        if not isinstance(pr_data, dict):
            raise BaselineResolutionError("Evento 'pull_request' não contém o objeto 'pull_request'.")
        base_obj = pr_data.get("base")
        if not isinstance(base_obj, dict):
            raise BaselineResolutionError("Objeto 'pull_request' não contém o campo 'base'.")
        base_sha = base_obj.get("sha")
        if not base_sha:
            raise BaselineResolutionError("Campo 'pull_request.base.sha' ausente ou vazio no evento.")
        return validate_full_sha(str(base_sha))

    effective_ref = git_ref or event_data.get("ref")
    if effective_ref != MAIN_REF:
        raise BaselineResolutionError(
            f"Evento 'push' permitido exclusivamente para a ref '{MAIN_REF}'; recebido: '{effective_ref}'."
        )

    before_sha = event_data.get("before")
    if not before_sha:
        raise BaselineResolutionError("Campo 'before' ausente ou vazio no evento push da branch main.")
    return validate_full_sha(str(before_sha))


def get_current_head_sha(git_cwd: Path | None = None) -> str:
    """Obtém o SHA do HEAD atual no repositório git local."""
    proc = subprocess.run(
        ["git", "rev-parse", "HEAD"],
        capture_output=True,
        text=True,
        check=False,
        cwd=git_cwd,
    )
    if proc.returncode != 0:
        raise BaselineResolutionError(f"Erro ao obter o HEAD atual via Git: {proc.stderr.strip()}.")
    return validate_full_sha(proc.stdout.strip())


def verify_not_current_head(commit_sha: str, git_cwd: Path | None = None) -> None:
    """Impede que o baseline seja idêntico ao próprio HEAD atual."""
    head_sha = get_current_head_sha(git_cwd)
    if commit_sha == head_sha:
        raise BaselineResolutionError(
            f"O commit baseline '{commit_sha}' é idêntico ao HEAD candidato atual; auto-comparação proibida."
        )


def check_commit_in_git(commit_sha: str, git_cwd: Path | None = None) -> bool:
    """Verifica se o objeto commit existe localmente no git."""
    proc = subprocess.run(
        ["git", "rev-parse", "--verify", "--quiet", f"{commit_sha}^{{commit}}"],
        capture_output=True,
        check=False,
        cwd=git_cwd,
    )
    return proc.returncode == 0


def fetch_commit_if_needed(
    commit_sha: str, remote: str = "origin", git_cwd: Path | None = None
) -> None:
    """Garante que o commit esteja presente localmente buscando do remoto se necessário."""
    if check_commit_in_git(commit_sha, git_cwd):
        return

    fetch_proc = subprocess.run(
        ["git", "fetch", "--depth=1", remote, commit_sha],
        capture_output=True,
        text=True,
        check=False,
        cwd=git_cwd,
    )
    if fetch_proc.returncode != 0:
        subprocess.run(
            ["git", "fetch", remote, commit_sha],
            capture_output=True,
            check=False,
            cwd=git_cwd,
        )

    if not check_commit_in_git(commit_sha, git_cwd):
        raise BaselineResolutionError(
            f"Commit baseline '{commit_sha}' não pôde ser localizado ou baixado do remoto '{remote}'."
        )


def resolve_baseline_sha(
    event_name: str | None = None,
    event_path: Path | None = None,
    git_ref: str | None = None,
    manual_base: str | None = None,
    remote: str = "origin",
    git_cwd: Path | None = None,
) -> str:
    """Pipeline completo de resolução e validação do commit baseline."""
    if manual_base:
        base_sha = validate_full_sha(manual_base)
        verify_not_current_head(base_sha, git_cwd)
        fetch_commit_if_needed(base_sha, remote, git_cwd)
        return base_sha

    effective_event_name = event_name or os.getenv("GITHUB_EVENT_NAME")
    if not effective_event_name:
        raise BaselineResolutionError(
            "Nome do evento do GitHub não informado (defina GITHUB_EVENT_NAME ou passe --event-name)."
        )

    effective_path_str = str(event_path) if event_path else os.getenv("GITHUB_EVENT_PATH")
    if not effective_path_str:
        raise BaselineResolutionError(
            "Caminho do payload do evento não informado (defina GITHUB_EVENT_PATH ou passe --event-path)."
        )

    effective_ref = git_ref or os.getenv("GITHUB_REF")
    payload = load_event_payload(Path(effective_path_str))
    base_sha = extract_base_sha(effective_event_name, payload, effective_ref)
    verify_not_current_head(base_sha, git_cwd)
    fetch_commit_if_needed(base_sha, remote, git_cwd)
    return base_sha


def parse_args(args: list[str] | None = None) -> argparse.Namespace:
    """Analisa argumentos da CLI."""
    parser = argparse.ArgumentParser(
        description="Resolvedor robusto de baseline imutável a partir do evento do GitHub Actions."
    )
    parser.add_argument("--event-name", default=None, help="Nome do evento (pull_request, push).")
    parser.add_argument("--event-path", default=None, help="Caminho do arquivo JSON do evento.")
    parser.add_argument("--ref", default=None, help="Git ref (ex: refs/heads/main).")
    parser.add_argument("--manual-base", default=None, help="Base SHA manual para testes controlados.")
    parser.add_argument("--remote", default="origin", help="Nome do repositório remoto.")
    return parser.parse_args(args)


def main(args: list[str] | None = None) -> int:
    """Ponto de entrada da CLI."""
    parsed = parse_args(args)
    event_path = Path(parsed.event_path) if parsed.event_path else None
    try:
        resolved_sha = resolve_baseline_sha(
            event_name=parsed.event_name,
            event_path=event_path,
            git_ref=parsed.ref,
            manual_base=parsed.manual_base,
            remote=parsed.remote,
        )
        print(resolved_sha)
        return 0
    except BaselineResolutionError as exc:
        print(f"FALHA AO RESOLVER BASELINE: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
