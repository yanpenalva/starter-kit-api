# Preflight — FEAT-9

**Revisão independente Oracle (ses_f00b14f02ffecUjnB5Iz5v7Epb): PASS** para a primeira fatia (2026-10-03), com exigências incorporadas ao plano. Dono humano yanpenalva; Git automático sob [EXC-0001](../../exceptions/EXC-0001.json) (branch `feat/wave-1-api-foundations`, alvo `main`, `origin` — registrados).

Requisitos do preflight incorporados:

1. Índice único parcial de CPF `WHERE cpf IS NOT NULL` **aprovado**; teste de **concorrência de insert duplicado obrigatório** (segunda conexão com `lock_timeout`, aguardando a entrada do índice único).
2. Escopo reversível: arquivos novos (migration/model/rule/factory/testes); rollback = remoção + `migrate:fresh` apenas no banco `starter_api_test` (nome verificado por asserção; banco dev ausente/inalterado).
3. Gates executáveis: `docker compose --profile test run --rm test` (Pest/PG isolado), `composer lint/analyse` no container PHP 8.5, gates CSD.

Sem RBAC/audit/seeders de privilégio (wave 2+); sem admin real; fase avança para execute.
