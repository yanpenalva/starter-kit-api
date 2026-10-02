"""Create development-only configuration without overwriting an existing .env."""

from pathlib import Path
import os
import secrets


def main() -> None:
    destination = Path(__file__).resolve().parent.parent / ".env"
    if destination.exists():
        raise SystemExit(".env already exists; keep it and configure it manually.")

    template = destination.with_name(".env.example").read_text()
    template = template.replace("\nDB_PASSWORD=\n", f"\nDB_PASSWORD={secrets.token_hex(24)}\n")
    # Replace exactly the test key: the generated development key stays separate.
    template = template.replace("TEST_DB_PASSWORD=\n", f"TEST_DB_PASSWORD={secrets.token_hex(24)}\n")
    template += f"\nLOCAL_UID={os.getuid()}\nLOCAL_GID={os.getgid()}\n"
    with destination.open("x") as configuration:
        configuration.write(template)
    destination.chmod(0o600)
    print("Created local .env with random development/test passwords. Generate APP_KEY using Docker.")


if __name__ == "__main__":
    main()
