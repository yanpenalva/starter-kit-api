# Discovery — FEAT-9

[API #9](https://github.com/yanpenalva/starter-kit-api/issues/9) é progressiva. Wave 1, primeira fatia: identidade do User. Base factual: ADR 0004 (cadastro, verificação, ativação, CPF), scaffold Laravel 13/PHP 8.5, PostgreSQL 18 isolado (compose: `postgres` dev / `postgres-test` teste), QUALITY_GATES e quality.md vigentes. Board: API #9 In progress (movido pelo pai).

Verdade de configuração observada: compose define `SESSION_DRIVER=file` (dev) e `array` (teste); os fallbacks `database`/`sqlite` em config são inertes sob o compose real. Nenhuma tabela de sessions é necessária nesta fatia.

Fora desta fatia (waves/issues donas): RBAC/catálogo, schema de privilégios, audit (Activitylog), seeders privilegiados, bootstrap admin (wave 2, #12/#16/#19); endpoints públicos de perfil/DTO (wave 3, #13).
