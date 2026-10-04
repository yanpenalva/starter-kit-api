# Stack and Tooling

Scaffold runtime/dependency versions are resolved in composer.lock and Docker image digests; application feature packages remain planned until their owning issues. Verification evidence is recorded in FEAT-7/8/24.

| Area | Technology | Version | State |
| --- | --- | --- | --- |
| Runtime | PHP | 8.5.11 | Docker runtime; host PHP is not the validation target |
| Framework | Laravel | 13.34.0 | API-only scaffold |
| Database | PostgreSQL | 18.6 | Separate development/test containers |
| Queues/cache | Redis | 8.4.0; pinned image digest | Optional async profile; application integration deferred |
| Authentication | Laravel Sanctum | NOT FOUND | First-party SPA cookies/session/CSRF |
| RBAC | Spatie Laravel Permission | NOT FOUND | Planned |
| Audit | Spatie Laravel Activitylog | v5 | Accepted in ADR 0005; dependency resolution/installation pending |
| OpenAPI | Scramble | dedoc/scramble 0.13.47 (installed, exact pin; composer.lock `d1597812fa8ce40a257d83c527bec28cb60de7a5`) | Stage B: real export from implemented auth routes via `config/scramble.php` + `ScrambleDocServiceProvider` + `ContractDocumentTransformer`; contract transport uses local orchestrator with authorized gh CLI (private repos, no consumer PAT) |
| Testing/quality | Pest, Pint, Larastan | composer.lock | Configured; Larastan level 10 without baseline |
| Context kit | @owlcodium/context-spec-develop | 1.2.1 | Installed in project scope for Codex |
| CSD installer | Node.js / npm | Node >=22 | Tooling only, not application runtime |
| Context validator | Python 3 | Minimum version NOT FOUND | No third-party Python dependencies |

Application package manager: Composer; composer.lock records resolved versions. Development image versions/digests: Dockerfile and compose.yaml. Production images, deployment platform and hosting: `NOT FOUND`.

Resolved quality tools: Pest 4.7.8, Pint 1.32.1, Larastan 3.12.2 (level 10), Pest Laravel plugin 4.1.0. Optional Mailpit image: 1.28.0, pinned by digest. PHP includes mbstring/OPcache natively; Dockerfile adds pdo_pgsql and zip. Versions reflect actual runtime/lockfile verification, not production deployment.

OpenAPI contract tooling: Stage A implements producer-side artifact provenance verification (`scripts/verify_contract_artifact.py`) and a guarded workflow draft (`.github/workflows/openapi.yml`). Stage B (this branch, PENDING independent review): Scramble 0.13.47 installed; real export from `routes/api.php` auth surface via `config/scramble.php` (`api/v1`, info 1.0.0), `App\Providers\ScrambleDocServiceProvider` (admin Gate `viewApiDocs`), and `App\OpenApi\ContractDocumentTransformer` (sessionCookie + XSRF overlay, CSRF route, real error contracts); snapshot `contracts/openapi.json` (SHA be6e4acd79e8bb9383b39b1e698ba9e3e147cda3c3cf12db223e82cdeee88b8d, 20447 bytes, LC_ALL=C 2x identical); producer workflow exports + uploads `openapi-<fullSHA>` on main; quality gates enforce determinism + snapshot freshness + structural validation. Contract transport uses the local orchestrator with existing authorized `gh` CLI for private repository synchronization; the consumer receives a durably committed spec, TypeScript types, and provenance receipt without requiring a cross-repository CI token or PAT.


