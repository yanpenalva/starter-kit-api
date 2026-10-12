from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[2]


class ContractReleaseDocsTest(unittest.TestCase):
    def test_release_guide_matches_the_producer_workflow(self) -> None:
        workflow = (ROOT / ".github/workflows/openapi.yml").read_text(encoding="utf-8")
        guide = (ROOT / "docs/contract-release.md").read_text(encoding="utf-8")
        normalized_guide = " ".join(guide.split())

        self.assertIn("workflow_dispatch:", workflow)
        self.assertNotIn("\n  push:", workflow)
        self.assertIn("refs/heads/main", workflow)
        self.assertIn("dedoc/scramble", workflow)
        self.assertIn("LC_ALL: C", workflow)
        self.assertIn("cmp storage/app/openapi-pass1.json storage/app/openapi-pass2.json", workflow)
        self.assertIn("retention-days: 90", workflow)

        for claim in (
            "workflow_dispatch",
            "main",
            "0.13.47",
            "LC_ALL=C",
            "90 dias",
            "não cria commit",
            "contracts/openapi.json",
        ):
            with self.subTest(claim=claim):
                self.assertIn(claim, normalized_guide)

    def test_onboarding_links_to_both_canonical_guides(self) -> None:
        onboarding = (ROOT / "docs/onboarding.md").read_text(encoding="utf-8")

        self.assertIn("(contract-release.md)", onboarding)
        self.assertIn("(openapi-contract-recipe.md)", onboarding)
        self.assertIn("(vertical-slice-recipe.md)", onboarding)

    def test_release_guide_links_to_detailed_provenance_recipe(self) -> None:
        guide = (ROOT / "docs/contract-release.md").read_text(encoding="utf-8")

        self.assertIn("(openapi-contract-recipe.md)", guide)

    def test_vertical_slice_recipe_covers_project_boundaries(self) -> None:
        recipe = (ROOT / "docs/vertical-slice-recipe.md").read_text(encoding="utf-8")

        for requirement in (
            "TDD",
            "ActsAsSanctumUser",
            "composer lint",
            "composer analyse",
            "ilike",
            "contracts/openapi.json",
            "contract-release.md",
        ):
            with self.subTest(requirement=requirement):
                self.assertIn(requirement, recipe)


if __name__ == "__main__":
    unittest.main()
