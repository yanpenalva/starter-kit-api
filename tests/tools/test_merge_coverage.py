"""Focused test for scripts/merge-coverage.php (FEAT-23 W0).

Self-contained: builds minimal Clover fixtures, merges them through the project
merger and asserts behaviour (union of hits, honest metric recomputation, no
double counting, preserved denominators, safe failure).
"""
from __future__ import annotations

import os
import re
import shutil
import subprocess
import unittest
from unittest.mock import patch
import xml.etree.ElementTree as ET
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
MERGER = ROOT / "scripts/merge-coverage.php"


def resolve_php() -> str:
    php = os.environ.get("PHP_BIN") or shutil.which("php8.5") or shutil.which("php")
    if php:
        return php
    raise RuntimeError("PHP not found; export PHP_BIN=/opt/data/bin/php8.5")


PHP = resolve_php()
# .context/policies/core/code-quality.md forbids else/elseif in production code.
FORBIDDEN_BRANCH = re.compile(r"\b(?:else|elseif|elif)\b")

FILE_A = "app/Foo.php"
FILE_B = "app/Bar.php"
FILE_ONLY_A = "app/OnlyA.php"
FILE_UNLISTED = "app/Unlisted.php"


def only_a_file(*, unlisted: bool) -> str:
    """File present in the first report only; `unlisted` adds a metrics-only statement."""
    statements, covered = (2, 1) if unlisted else (1, 1)

    return f"""      <file name="{FILE_ONLY_A}">
        <class name="OnlyA" namespace="App">
          <metrics complexity="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="{statements}" coveredstatements="{covered}" elements="{statements + 1}" coveredelements="{covered + 1}"/>
        </class>
        <line num="5" type="method" name="run" visibility="public" complexity="1" crap="1" count="3"/>
        <line num="6" type="stmt" count="3"/>
        <metrics loc="7" ncloc="7" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="{statements}" coveredstatements="{covered}" elements="{statements + 1}" coveredelements="{covered + 1}"/>
      </file>"""


def clover(*, m10: int, s11: int, s12: int, m18: int, s19: int, include_bar: bool = False,
           include_only_a: bool = False, include_package_metrics: bool = False,
           extra_line: bool = False, only_a_unlisted: bool = False) -> str:
    package_metrics = (
        '      <metrics files="0" loc="0" ncloc="0" classes="0" methods="0" coveredmethods="0" '
        'conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="0" '
        'coveredelements="0"/>\n'
    ) if include_package_metrics else ""
    extra = '        <line num="13" type="stmt" count="7"/>\n' if extra_line else ""
    bar = ""
    if include_bar:
        bar = f"""      <file name="{FILE_B}">
        <class name="Bar" namespace="App">
          <metrics complexity="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
        </class>
        <line num="5" type="method" name="run" visibility="public" complexity="1" crap="1" count="0"/>
        <line num="6" type="stmt" count="0"/>
        <metrics loc="7" ncloc="7" classes="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
      </file>"""
    only_a = ""
    if include_only_a:
        only_a = only_a_file(unlisted=only_a_unlisted)
    # Project metrics intentionally stale: the merger must recompute them.
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1" name="fixture">
    <package name="App">
      <file name="{FILE_A}">
        <class name="Foo" namespace="App">
          <metrics complexity="2" methods="2" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="3" coveredstatements="0" elements="5" coveredelements="0"/>
        </class>
        <line num="10" type="method" name="run" visibility="public" complexity="1" crap="1" count="{m10}"/>
        <line num="11" type="stmt" count="{s11}"/>
        <line num="12" type="stmt" count="{s12}"/>
{extra}        <line num="18" type="method" name="other" visibility="public" complexity="1" crap="1" count="{m18}"/>
        <line num="19" type="stmt" count="{s19}"/>
        <metrics loc="20" ncloc="20" classes="1" methods="2" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="3" coveredstatements="0" elements="5" coveredelements="0"/>
      </file>
{bar}
{only_a}
{package_metrics}    </package>
    <metrics files="1" loc="0" ncloc="0" classes="0" methods="0" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="0" coveredelements="0"/>
  </project>
</coverage>
"""


def clover_with_unlisted_statement(*, unlisted_covered: bool) -> str:
    """Clover fixture whose file metrics exceed its <line> entries.

    PHPUnit Clover accounts for an executable method start line through the
    method entry alone, so `statements` can be one higher than the number of
    `<line type="stmt">` nodes; a merger that recomputes the denominator from
    the nodes alone drops that line and inflates the percentage.
    """
    covered = 2 if unlisted_covered else 1
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1" name="fixture">
    <package name="App">
      <file name="{FILE_UNLISTED}">
        <class name="Unlisted" namespace="App">
          <metrics complexity="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="{covered}" elements="3" coveredelements="{covered + 1}"/>
        </class>
        <line num="5" type="method" name="run" visibility="public" complexity="1" crap="1" count="1"/>
        <line num="6" type="stmt" count="1"/>
        <metrics loc="6" ncloc="6" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="{covered}" elements="3" coveredelements="{covered + 1}"/>
      </file>
    </package>
    <metrics files="1" loc="0" ncloc="0" classes="0" methods="0" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="0" coveredelements="0"/>
  </project>
</coverage>
"""


def attrib_ints(node: ET.Element) -> dict[str, int]:
    return {k: int(v) for k, v in node.attrib.items() if v.lstrip("-").isdigit()}


def metrics_of(path: Path, xpath: str) -> dict[str, int]:
    node = ET.parse(str(path)).getroot().find(xpath)
    assert node is not None, f"missing {xpath}"
    return attrib_ints(node)


def file_metrics(root: ET.Element) -> dict[str, dict[str, int]]:
    """Direct <metrics> child of each <file> (never the nested <class> one)."""
    metrics: dict[str, dict[str, int]] = {}
    for element in root.iter("file"):
        node = element.find("metrics")
        assert node is not None, f"missing file metrics for {element.attrib['name']}"
        metrics[element.attrib["name"]] = attrib_ints(node)

    return metrics


class MergeCoverageTest(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = Path(self._tmp_dir())
        self.tmp.mkdir(parents=True, exist_ok=True)
        self.a = self.tmp / "a.xml"
        self.b = self.tmp / "b.xml"
        self.out = self.tmp / "merged.xml"
        # Complementary hits: A covers 11, B covers 12 and 19.
        self.a.write_text(clover(m10=2, s11=2, s12=0, m18=0, s19=0, include_only_a=True), encoding="utf-8")
        self.b.write_text(clover(m10=0, s11=0, s12=4, m18=1, s19=1, include_bar=True), encoding="utf-8")

    def _tmp_dir(self) -> str:
        import tempfile
        return tempfile.mkdtemp(prefix="merge-cov-")

    def run_merger(self, *args: str) -> subprocess.CompletedProcess[str]:
        return subprocess.run([PHP, str(MERGER), *args], capture_output=True, text=True, cwd=str(ROOT))

    def test_missing_php_binary_has_actionable_error(self) -> None:
        with patch.dict(os.environ, {}, clear=True), patch("shutil.which", return_value=None):
            with self.assertRaisesRegex(RuntimeError, "export PHP_BIN=/opt/data/bin/php8.5"):
                resolve_php()

    def test_merge_unites_complementary_hits(self) -> None:
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        lines = {(l.attrib["num"], l.attrib["type"]): int(l.attrib["count"])
                 for l in ET.parse(str(self.out)).getroot().iter("line")
                 if l.attrib.get("num") in ("11", "12", "19")}
        self.assertEqual(lines, {("11", "stmt"): 2, ("12", "stmt"): 4, ("19", "stmt"): 1})

    def test_merge_keeps_exclusive_and_fully_uncovered_files(self) -> None:
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        names = {f.attrib["name"] for f in ET.parse(str(self.out)).getroot().iter("file")}
        self.assertEqual(names, {FILE_A, FILE_B, FILE_ONLY_A})
        bar = next(f for f in ET.parse(str(self.out)).getroot().iter("file")
                   if f.attrib["name"] == FILE_B)
        bar_counts = [int(l.attrib["count"]) for l in bar.findall("line")
                      if l.attrib.get("num") in ("5", "6")]
        self.assertEqual(bar_counts, [0, 0])
        self.assertEqual(metrics_of(self.out, "./project/metrics")["files"], 3)

    def test_merge_recomputes_metrics_honestly(self) -> None:
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        project = metrics_of(self.out, "./project/metrics")
        # Foo: 3 stmts + 2 methods = 5 elements, all covered after union.
        # OnlyA: 1 + 1 = 2 elements covered. Bar: 1 + 1 = 2, uncovered.
        self.assertEqual(project["statements"], 5)
        self.assertEqual(project["coveredstatements"], 4)
        self.assertEqual(project["methods"], 4)
        self.assertEqual(project["coveredmethods"], 3)
        self.assertEqual(project["elements"], 9)
        self.assertEqual(project["coveredelements"], 7)

    def test_merge_is_idempotent_without_double_counting(self) -> None:
        self.assertEqual(self.run_merger(str(self.a), str(self.b), str(self.out)).returncode, 0)
        again = self.tmp / "again.xml"
        proc = self.run_merger(str(self.out), str(self.out), str(again))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        for key in ("statements", "coveredstatements", "methods", "coveredmethods",
                    "elements", "coveredelements", "files"):
            with self.subTest(metric=key):
                self.assertEqual(metrics_of(again, "./project/metrics")[key],
                                 metrics_of(self.out, "./project/metrics")[key])

    def test_merge_sums_file_metrics_into_totals_and_classes(self) -> None:
        """W0-1/W0-5: file metrics come from the <file>, never from its <class>."""
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        root = ET.parse(str(self.out)).getroot()
        files = file_metrics(root)
        project = metrics_of(self.out, "./project/metrics")
        for key in ("loc", "ncloc", "classes"):
            with self.subTest(metric=key):
                self.assertEqual(project[key], sum(metrics[key] for metrics in files.values()))
        self.assertEqual(project["loc"], 34)
        self.assertEqual(project["ncloc"], 34)
        self.assertEqual(project["classes"], 3)
        for element in root.iter("file"):
            class_metrics = element.find("class/metrics")
            assert class_metrics is not None, f"missing class metrics for {element.attrib['name']}"
            for key in ("methods", "coveredmethods", "statements", "coveredstatements",
                        "elements", "coveredelements"):
                with self.subTest(file=element.attrib["name"], metric=key):
                    self.assertEqual(attrib_ints(class_metrics)[key], files[element.attrib["name"]][key])

    def test_merge_recomputes_existing_package_metrics(self) -> None:
        """W0-4: a <package><metrics/></package> node must not stay stale."""
        self.a.write_text(clover(m10=2, s11=2, s12=0, m18=0, s19=0,
                                 include_package_metrics=True), encoding="utf-8")
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        package_metrics = ET.parse(str(self.out)).getroot().find("./project/package/metrics")
        assert package_metrics is not None, "package metrics node was dropped"
        project = metrics_of(self.out, "./project/metrics")
        for key, value in attrib_ints(package_metrics).items():
            with self.subTest(metric=key):
                self.assertEqual(value, project[key])

    def test_unlisted_executable_statement_keeps_denominator_and_hits(self) -> None:
        """Coverage must not be inflated when metrics exceed the <line> entries."""
        for unlisted_covered, expected in ((True, (2, 2)), (False, (1, 2))):
            with self.subTest(unlisted_covered=unlisted_covered):
                source = self.tmp / f"unlisted-{unlisted_covered}.xml"
                merged = self.tmp / f"unlisted-{unlisted_covered}-merged.xml"
                source.write_text(clover_with_unlisted_statement(unlisted_covered=unlisted_covered),
                                  encoding="utf-8")
                proc = self.run_merger(str(source), str(source), str(merged))
                self.assertEqual(proc.returncode, 0, msg=proc.stderr)
                project = metrics_of(merged, "./project/metrics")
                self.assertEqual((project["coveredstatements"], project["statements"]), expected)
                self.assertEqual(project["elements"], 3)
                self.assertEqual(project["coveredelements"], expected[0] + 1)

    def test_merge_keeps_denominator_of_files_only_in_the_first_report(self) -> None:
        self.a.write_text(clover(m10=2, s11=2, s12=0, m18=0, s19=0, include_only_a=True,
                                 only_a_unlisted=True), encoding="utf-8")
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        project = metrics_of(self.out, "./project/metrics")
        # Foo 3 statements (3 covered) + Bar 1 (0 covered) + OnlyA 2 (1 covered).
        self.assertEqual(project["statements"], 6)
        self.assertEqual(project["coveredstatements"], 4)
        self.assertEqual(project["files"], 3)

    def test_merge_keeps_lines_of_both_reports_in_ascending_order(self) -> None:
        self.b.write_text(clover(m10=0, s11=0, s12=4, m18=1, s19=1, include_bar=True,
                                 extra_line=True), encoding="utf-8")
        proc = self.run_merger(str(self.a), str(self.b), str(self.out))
        self.assertEqual(proc.returncode, 0, msg=proc.stderr)
        foo = next(f for f in ET.parse(str(self.out)).getroot().iter("file")
                   if f.attrib["name"] == FILE_A)
        nums = [int(line.attrib["num"]) for line in foo.findall("line")]
        self.assertEqual(nums, sorted(nums))
        self.assertEqual(nums, [10, 11, 12, 13, 18, 19])
        only_in_b = foo.find("line[@num='13']")
        assert only_in_b is not None, "line present only in the second report was dropped"
        self.assertEqual(only_in_b.attrib["count"], "7")

    def test_merger_has_no_forbidden_fallback_branch(self) -> None:
        # .context/policies/core/code-quality.md: no else/elseif in production code.
        self.assertEqual(FORBIDDEN_BRANCH.findall(MERGER.read_text(encoding="utf-8")), [])

    def test_missing_input_fails_without_touching_valid_output(self) -> None:
        self.out.write_bytes(b"<valid/>")
        before = self.out.read_bytes()
        proc = self.run_merger(str(self.tmp / "nope.xml"), str(self.b), str(self.out))
        self.assertNotEqual(proc.returncode, 0)
        self.assertEqual(self.out.read_bytes(), before)

    def test_invalid_xml_fails_without_touching_valid_output(self) -> None:
        bad = self.tmp / "bad.xml"
        bad.write_text("not xml at all <", encoding="utf-8")
        self.out.write_bytes(b"<valid/>")
        before = self.out.read_bytes()
        proc = self.run_merger(str(bad), str(self.b), str(self.out))
        self.assertNotEqual(proc.returncode, 0)
        self.assertEqual(self.out.read_bytes(), before)


if __name__ == "__main__":
    unittest.main()
