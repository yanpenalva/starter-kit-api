# Project Overview

- Name: `starter-kit-api`.
- Repository: https://github.com/yanpenalva/starter-kit-api
- Onboarding issue: https://github.com/yanpenalva/starter-kit-api/issues/5
- Purpose: independent REST API for the new Starter Kit ecosystem.
- Stage: Laravel 13 API with Sanctum session/CSRF authentication, identity flows, user/role administration, RBAC policies, audit feed, generated OpenAPI, and CI quality/compatibility gates. PHP 8.5 runtime and isolated PostgreSQL 18 test service are configured. Scaffold evidence is in FEAT-7/8/24; contract workflow evidence is in FEAT-11. Later refinements are tracked by their slice work records and feature branches.
- API and frontend are independent repositories, with independent deployments and versioning.
- End-to-end issue sequencing, parallel delivery boundaries and estimated complexity: [roadmap](roadmap.md). This planning artifact covers both repositories; each issue keeps its own local CSD work item.
- `starter-kit-laravue` is exclusively a read-only reference.
- Business owners, production environments, domains and infrastructure: `NOT FOUND`.
- Evidence: repository source, `composer.lock`, CI workflow files, and the linked work-item verification records. Production deployment is not established by local or CI feature checks.
