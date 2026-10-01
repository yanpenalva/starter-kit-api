# Quality Configuration

Normative baseline: `.context/policies/core/code-quality.md`. Governance mode is `starter`: application quality tools/thresholds remain `NOT FOUND` until scaffold, without suppressing context validation.

Executed/required CSD checks:

```sh
python3 scripts/validate_context.py --strict
python3 scripts/validate_context.py --strict --examples
python3 -m unittest discover -s tests -v
npx @owlcodium/context-spec-develop@1.2.1 doctor --scope project
```

The unmodified official validator is in `scripts/vendor/csd/validate_context.py`; source commit and SHA-256 are recorded in `.csd-source.json`. The local entrypoint disables only the public-kit neutrality scan (which rejects Laravel/Vue application context). `--examples` validates a suite if present, otherwise reports its absence; real work-item validation always runs. No example suite is installed and onboarding creates no work item.

All other upstream structural, schema/config, link, placeholder, classification, governance, approval and active-work-item checks remain enabled. Thin reference adapters required by upstream validation are present; only Codex is activated by the project installer.

Application test/static-analysis/lint/build commands, measured complexity/coverage/duplication thresholds and quality owner: `NOT FOUND`. API intends Pest/Pint/Larastan; frontend tooling is pending. These are plans, not claimed passing checks.
