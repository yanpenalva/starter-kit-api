# Stack and Tooling

Runtime and dependency versions are resolved in `composer.lock` and Docker image digests. Application feature evidence is recorded by the owning work items; the contract workflow is tracked in FEAT-11.

| Area | Technology | Version | State |
| --- | --- | --- | --- |
| Runtime | PHP | 8.5.11 | Docker runtime; host PHP is not the validation target |
| Framework | Laravel | 13.34.0 | API-only scaffold |
| Database | PostgreSQL | 18.6 | Separate development/test containers |
| Queues/cache | Redis | 8.4.0; pinned image digest | Optional async profile; application integration deferred |
| Authentication | Laravel Sanctum | 4.3.3 | First-party SPA cookies/session/CSRF; no bearer/PAT auth |
| RBAC | Spatie Laravel Permission | 8.3.0 | Closed enum/seeder catalogue, gates, policies and role administration |
| Audit | Spatie Laravel Activitylog | 5.1.1 | Allowlisted actions/properties, protected feed, 90-day retention |
| OpenAPI | Scramble | dedoc/scramble 0.13.47 (exact pin; composer.lock `d1597812fa8ce40a257d83c527bec28cb60de7a5`) | Runtime export, manual main-only producer workflow, artifact provenance, snapshot freshness and compatibility gate |
| Testing/quality | PHPUnit, Pint, Larastan | composer.lock | Configured; Larastan level 10 without baseline |
| Context kit | @owlcodium/context-spec-develop | 1.2.1 | Installed in project scope for Codex |
| CSD installer | Node.js / npm | Node >=22 | Tooling only, not application runtime |
| Context validator | Python 3 | Minimum version NOT FOUND | No third-party Python dependencies |

Application package manager: Composer; composer.lock records resolved versions. Development image versions/digests: Dockerfile and compose.yaml. Production images, deployment platform and hosting: `NOT FOUND`.

Resolved quality tools: PHPUnit 12.5.37, Pint 1.32.1, Larastan 3.12.2 (level 10). Optional Mailpit image: 1.28.0, pinned by digest. PHP includes mbstring/OPcache natively; Dockerfile adds pdo_pgsql and zip. Versions reflect actual runtime/lockfile verification, not production deployment.

OpenAPI contract tooling: `.github/workflows/openapi.yml` (`OpenAPI Contract`) runs by manual dispatch on `main`, pins Scramble `0.13.47`, enforces two byte-identical exports under `LC_ALL=C`, and uploads `openapi-<fullSHA>` for 90 days. `.github/workflows/quality.yml` validates the committed snapshot and breaking changes using pinned `oasdiff 1.33.0`. Provenance tooling records and verifies workflow/artifact metadata and independent ZIP/file hashes; these prove transfer integrity, not cryptographic authorship. See the [contract release guide](../../docs/contract-release.md) and [FEAT-11](../work/FEAT-11/work-item.json).
