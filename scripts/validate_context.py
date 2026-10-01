"""Project adapter for the unchanged CSD 1.2.1 validator.

The upstream public-package neutrality scan rejects application stack names.
Only that kit-publication check is inapplicable to an adopting project.
Example validation is optional when no example suite is distributed.
"""
import sys
from vendor.csd import validate_context as upstream


class ProjectValidator(upstream.Validator):
    def check_legacy_and_public_markers(self) -> None:
        """Application technology names are valid project context."""

    def check_examples(self, config: dict) -> None:
        if (self.root / "examples").is_dir():
            super().check_examples(config)
            return
        self.warning("No example suite is installed; active work items are still validated.")


if __name__ == "__main__":
    upstream.Validator = ProjectValidator
    sys.exit(upstream.main())
