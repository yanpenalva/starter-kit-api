# Review — FEAT-20

> **STATUS: RELEASED CONDITIONALLY — work item `active`; PR/integração
> autorizada sob EXC-0001 (autorização de continuação do parent,
> 2026-10-04). Bloqueio de proveniência ENCERRADO pelo parent.**
>
> - Revisão independente oracle round 1: **PASS WITH CONDITIONS em
>   `9c61e18`** (condições F2–F6 atribuídas ao fixer).
> - Re-review oracle round 2: **PASS WITH CONDITIONS em
>   `ccee7f42214b91ba394d6ddcae37a0c10d596f6c`** — F2–F6 aplicadas,
>   zero-diff core vs `origin/main` confirmado de forma independente,
>   gates técnicos auditáveis (Pest **53 passed / 248 assertions**).
> - **Procedência de `9c5f102` RESOLVIDA (decisão parent, 2026-10-04)**:
>   explorer comprovou via lifecycle OpenCode (log + DB) que o commit foi
>   realizado em **2026-10-04T14:16:28Z pela sessão fixer antiga retomada
>   `ses_efbddf033ffevWJI0m8ZqB6Z2U`**, distinta da sessão fixer seguinte
>   desta frente; conteúdo = edições novas da sessão antiga + adendo no
>   execution.md da mesma sessão, todo ele em escopo FEAT-20; nenhum
>   escritor ativo/locks restantes. Ocorrência histórica preservada nos
>   registros abaixo (round 2); bloqueio de governança removido.
> - **Liberação condicional**: PR/CI/merge condicional liberados, sujeitos
>   aos checks finais no SHA exato (context/application e qualquer
>   required), regra "base mudou → sem merge", sem `--admin`/`--force`.
>   **Nenhuma aprovação GitHub é alegada** — re-review por agente não
>   equivale a approving review; review independente continua não-GitHub.

## Inputs

- Request: roadmap onda 2, API #20 (filas Redis + Mailpit locais, worker/scheduler, afterCommit, retry/backoff, falhas observáveis, isolamento dev/test, guardas F1–F4).
- Spec: `.context/work/FEAT-20/spec.md`
- Plan: `.context/work/FEAT-20/plan.md` (subtasks/waves, incl. FEAT-20-RECOVERY)
- Diff revisado pelo oracle: `git show 9c61e18` (base `065ff90`)
- Diff round 2 (correções): `eef1206` (merge origin/main `4f7c74d`) + `9c5f102`
- Verification: `.context/work/FEAT-20/verification.md` (gates 2026-10-03 e 2026-10-04)
- Applicable policies: `.context/policies/core/*`
- Exceptions: EXC-0001 (branch de entrega `feat/wave-2-async-recovery` → `main`, remote `origin`; registrada em execution.md)

## Oracle verdict (independent review, 2026-10-04, em `9c61e18`)

**PASS WITH CONDITIONS** — revisor: oracle (revisão independente, agente distinto do fixer; GitHub approving review NÃO alegado). Condições atribuídas ao implementador:

- **F2**: foot-gun do `name:` global `starter-kit-api` em `compose.yaml` — exigia warning explícito (colisão entre worktrees, nunca `down -v` na stack primária) e garantia de `-p starter-wave2-async` + portas isoladas nos scripts, sem fingir que `${COMPOSE_PROJECT_NAME:-…}` sozinho elimina o risco.
- **F5**: `infra:smoke-mail`/`infra:fail-job` sem a guarda `local/testing` do `infra:probe` — exigia mesma guarda + negativos de prod simulado provando nenhum envio/job.
- **F3**: `worker`/`scheduler` sem política de restart — exigia `unless-stopped` dev-only se consistente com compose/testes.
- **F6**: `MAILPIT_API_URL` implícito — exigia pin explícito no serviço `test` e default claro/documentado sem quebrar async.
- **F4**: healthcheck baseado só em probe — limitação aceita dev-only, mas exigia documentação explícita de que não garante consumo de jobs.

## Correct

- Arquitetura async (perfis compose, anchor sem deps Redis/Mailpit, after_commit default false) aprovada pelo oracle em `9c61e18`.
- Core-owned files com diff zero vs `origin/main` após merge `eef1206` (verificação independente do fixer: `git diff origin/main` vazio em composer.json/lock, User, bootstrap, providers, seeders, Support, migrations 000002/000003 etc.; única sobreposição `routes/console.php` = 11 linhas do bloco consentido).

## Problems and risks

- Round 1 (F1): resolvido em `9c61e18` (guarda no registro de schedule + probe).
- Round 2 (F2–F6): TODAS aplicadas em `9c5f102` — warning F2 no compose + guarda no script; guardas F5 nos 2 comandos + 2 negativos novos (Mailpit inbox e `failed_jobs` intocados em prod simulado); F3 `restart: unless-stopped` dev-only validado por `compose config`; F6 `MAILPIT_API_URL` pinado no serviço test + documentado em `config/mail.php`/`.env.example`; F4 comentários de limitação nos healthchecks.
- Risco residual: comportamento de `restart: unless-stopped` após restart do daemon não exercitado em runtime (só validação de config) — aceitável dev-only.
- Governança: commit `9c5f102` apareceu no reflog como não-executado pelo fixer da sessão; conteúdo conferido contra as edições da sessão (idêntico) e alegação de zero-diff core re-verificada de forma independente; anomalia reportada ao orquestrador para atribuição. Gate fresco completo executado sobre este SHA.

## Baseline checklist

- [x] No new critical reliability or security issue. *(oracle round 1 + correções aplicadas; guardas F5 com negativos reais)*
- [x] No unexplained complexity or duplication regression. *(oracle round 1; round 2 só adicionou guardas/comentários/pin)*
- [x] Tests and actual command evidence are present. *(53 passed / 248 assertions; gates 2026-10-04 em verification.md)*
- [x] Each material check records command, scope, exit code, date and limitations. *(verification.md, incl. 1º erro Larastan não-reproduzível)*
- [x] Compatibility, data and rollback risks are addressed. *(core zero-diff; cleanup lane-scoped; primário intocado)*
- [x] Sensitive data and AI-use controls are addressed. *(sem segredos/PII; `.env` só `--env-file`)*
- [x] Subtask boundaries, wave dependencies and integration ownership are evidenced. *(PR/merge = parent; revisão oracle + integrador)*

## Verdict

Round 1 — oracle, em `9c61e18`: `PASS WITH CONDITIONS` (F2–F6 atribuídas).
Round 2 — re-review oracle, em `ccee7f42214b91ba394d6ddcae37a0c10d596f6c`: **`PASS WITH CONDITIONS`** — condições F2–F6 verificadas como aplicadas; zero-diff core vs `origin/main` reconfirmado; gates técnicos auditáveis (53/248, Pint 58, Larastan limpo, CSD suite).
Procedência — parent, 2026-10-04: **RESOLVIDA** (`9c5f102` atribuído à sessão fixer antiga retomada `ses_efbddf033ffevWJI0m8ZqB6Z2U`, 2026-10-04T14:16:28Z, escopo FEAT-20, sem escritores ativos; histórico preservado).
**Status final: `RELEASED CONDITIONALLY`** — PR/CI/merge condicional liberados sob EXC-0001 + autorização de continuação do parent; condições de merge: todos os checks em sucesso no SHA exato, nenhuma review requerida pendente, base `main` inalterada (se a base mudar: sem merge, retornar para review); sem `--admin`/`--force`; branch não apagada. **Nenhuma aprovação GitHub é alegada**; work item permanece `active` (fechamento/quadro = parent).
