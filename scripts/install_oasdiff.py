"""Instalador seguro e verificador de integridade da ferramenta fixada oasdiff.

Garante proveniência oficial, validação estrita de digest SHA256 antes da
extração e verificação de versão para prevenir execução de binários adulterados.
"""

from __future__ import annotations

import argparse
from dataclasses import dataclass
import hashlib
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import urllib.request

PINNED_VERSION: str = "1.33.0"
OFFICIAL_ASSET_NAME: str = "oasdiff_1.33.0_linux_amd64.tar.gz"
OFFICIAL_DOWNLOAD_URL: str = (
    "https://github.com/oasdiff/oasdiff/releases/download/v1.33.0/oasdiff_1.33.0_linux_amd64.tar.gz"
)
OFFICIAL_CHECKSUMS_URL: str = (
    "https://github.com/oasdiff/oasdiff/releases/download/v1.33.0/checksums.txt"
)
OFFICIAL_SHA256: str = (
    "43a4e328e2d13ba1552d760aa68d2485c75c5621f309f6ff64ae895188345247"
)
PINNED_BINARY_SHA256: str = (
    "fa65aae43c867e9f79da9cb121d3c1a113637883afe014c0680fd5b50696f416"
)
MAX_BINARY_BYTES: int = 64 * 1024 * 1024


class IntegrityError(Exception):
    """Lançada quando a integridade do binário ou asset é violada em modo fail-closed."""


@dataclass(frozen=True)
class InstallationResult:
    """Resultado imutável da instalação e verificação da ferramenta."""

    binary_path: Path
    version: str
    sha256: str


def compute_sha256(file_path: Path) -> str:
    """Calcula o digest SHA256 hexadecimal de um arquivo."""
    hasher = hashlib.sha256()
    with file_path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(65536), b""):
            hasher.update(chunk)
    return hasher.hexdigest()


def verify_asset_digest(archive_path: Path, expected_sha256: str = OFFICIAL_SHA256) -> None:
    """Verifica se o digest SHA256 do arquivo baixado corresponde ao oficial."""
    actual_sha256 = compute_sha256(archive_path)
    if actual_sha256 != expected_sha256.lower():
        raise IntegrityError(
            f"Digest SHA256 adulterado ou divergente para o asset '{archive_path.name}': "
            f"esperado={expected_sha256}, obtido={actual_sha256}."
        )


def verify_binary_digest(binary_path: Path, expected_sha256: str = PINNED_BINARY_SHA256) -> None:
    """Verifica o digest SHA256 do binário já extraído, antes de qualquer execução."""
    actual_sha256 = compute_sha256(binary_path)
    if actual_sha256 != expected_sha256.lower():
        raise IntegrityError(
            f"Digest SHA256 do binário extraído diverge do fixado: "
            f"esperado={expected_sha256}, obtido={actual_sha256}."
        )


def download_official_asset(target_file: Path, url: str = OFFICIAL_DOWNLOAD_URL) -> None:
    """Baixa o asset oficial a partir da URL informada."""
    try:
        with urllib.request.urlopen(url, timeout=30) as response, target_file.open("wb") as out:
            shutil.copyfileobj(response, out)
    except Exception as exc:
        raise IntegrityError(f"Falha ao baixar asset oficial de '{url}': {exc}.") from exc


def safe_extract_binary(archive_path: Path, target_dir: Path) -> Path:
    """Extrai estritamente o binário executável oasdiff sem symlinks ou traversal."""
    target_dir.mkdir(parents=True, exist_ok=True)
    destination = target_dir / "oasdiff"

    with tarfile.open(archive_path, "r:gz") as tar:
        try:
            member = tar.getmember("oasdiff")
        except KeyError as exc:
            raise IntegrityError(
                "Membro 'oasdiff' ausente no tarball oficial."
            ) from exc

        if member.name != "oasdiff" or "/" in member.name or ".." in member.name:
            raise IntegrityError(
                f"Nome de membro inesperado no tarball: '{member.name}'."
            )
        if member.issym() or member.islnk():
            raise IntegrityError("O arquivo do binário no tarball não pode ser symlink ou link rígido.")
        if not member.isfile():
            raise IntegrityError("O membro 'oasdiff' no tarball não é um arquivo regular.")
        if member.size <= 0 or member.size > MAX_BINARY_BYTES:
            raise IntegrityError(
                f"Tamanho do membro 'oasdiff' fora dos limites aceitáveis: {member.size} bytes."
            )

        extracted_file = tar.extractfile(member)
        if extracted_file is None:
            raise IntegrityError("Não foi possível ler o arquivo 'oasdiff' do arquivo tar.")
        with destination.open("wb") as target:
            shutil.copyfileobj(extracted_file, target)

    actual_size = destination.stat().st_size
    if actual_size != member.size:
        raise IntegrityError(
            f"Tamanho extraído diverge do metadado do tarball: esperado={member.size}, obtido={actual_size}."
        )
    destination.chmod(0o755)
    return destination


def verify_installed_binary(binary_path: Path, expected_version: str = PINNED_VERSION) -> str:
    """Executa o binário instalado e verifica se sua versão é exatamente a esperada."""
    if not binary_path.is_file():
        raise IntegrityError(f"Binário executável não encontrado em '{binary_path}'.")

    proc = subprocess.run([str(binary_path), "-v"], capture_output=True, text=True, check=False)
    if proc.returncode != 0:
        raise IntegrityError(f"Erro ao executar '{binary_path} -v': {proc.stderr.strip()}.")

    match = re.search(r"(\d+\.\d+\.\d+)", proc.stdout)
    if not match:
        raise IntegrityError(f"Formato de versão inesperado na saída de '{binary_path}': '{proc.stdout.strip()}'.")

    version = match.group(1)
    if version != expected_version:
        raise IntegrityError(
            f"Versão do binário instalado diverge da fixada: esperado={expected_version}, obtido={version}."
        )
    return version


def install_and_verify(
    target_dir: Path,
    source_archive: Path | None = None,
    expected_sha256: str = OFFICIAL_SHA256,
    expected_version: str = PINNED_VERSION,
    expected_binary_sha256: str = PINNED_BINARY_SHA256,
) -> InstallationResult:
    """Pipeline completo de instalação segura e verificação de integridade."""
    with tempfile.TemporaryDirectory() as temp_dir:
        archive_file = source_archive
        if archive_file is None:
            download_path = Path(temp_dir) / OFFICIAL_ASSET_NAME
            download_official_asset(download_path)
            archive_file = download_path

        verify_asset_digest(archive_file, expected_sha256)
        binary_path = safe_extract_binary(archive_file, target_dir)
        verify_binary_digest(binary_path, expected_binary_sha256)
        version = verify_installed_binary(binary_path, expected_version)
        return InstallationResult(
            binary_path=binary_path,
            version=version,
            sha256=expected_sha256,
        )


def parse_args(args: list[str] | None = None) -> argparse.Namespace:
    """Analisa argumentos da CLI."""
    parser = argparse.ArgumentParser(
        description="Instalador e verificador de integridade do oasdiff fixado."
    )
    parser.add_argument(
        "--target-dir",
        default="/usr/local/bin",
        help="Diretório onde o binário será instalado.",
    )
    parser.add_argument(
        "--source-archive",
        default=None,
        help="Arquivo tar.gz local opcional para verificação sem download.",
    )
    return parser.parse_args(args)


def main(args: list[str] | None = None) -> int:
    """Ponto de entrada do script."""
    parsed = parse_args(args)
    source_path = Path(parsed.source_archive) if parsed.source_archive else None
    try:
        result = install_and_verify(
            target_dir=Path(parsed.target_dir),
            source_archive=source_path,
        )
        print(
            f"OASDIFF INSTALADO COM SUCESSO: binário='{result.binary_path}' "
            f"versão='{result.version}' sha256='{result.sha256}'."
        )
        return 0
    except IntegrityError as exc:
        print(f"FALHA DE INTEGRIDADE: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
