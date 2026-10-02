"""Project adapter for the unchanged CSD 1.2.1 validator.

The upstream public-package neutrality scan rejects application stack names.
That kit-publication check is inapplicable to an adopting project. Composer's
installed dependencies are also excluded from Markdown-link and placeholder
checks because their package documentation is not project-owned context.
Example validation is optional when no example suite is distributed.
"""
import re
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

    def markdown_files(self):
        return [
            path for path in super().markdown_files()
            if "vendor" not in path.relative_to(self.root).parts
        ]

    def check_placeholders(self) -> None:
        if not self.strict:
            return
        marker = re.compile(r"<[A-Z][A-Z0-9_ /.-]*>")
        excluded = {
            ".context/templates", "examples", ".ai", "assets/template",
            "node_modules", "dist", "vendor",
        }
        for path in self.root.rglob("*"):
            if not path.is_file() or path.suffix not in {".md", ".json"}:
                continue
            relative = path.relative_to(self.root)
            if any(str(relative).startswith(prefix) for prefix in excluded):
                continue
            if marker.search(path.read_text(encoding="utf-8")):
                self.error(f"unresolved placeholder: {relative}")


if __name__ == "__main__":
    upstream.Validator = ProjectValidator
    sys.exit(upstream.main())
