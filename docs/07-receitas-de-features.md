# 🧭 Receitas de features: quero fazer X, por onde parto

Este guia responde "como seria em cima da arquitetura atual" para os tipos de
feature mais comuns. Cada receita lista arquivos na ordem de criação, o teste que
prova e as armadilhas. O processo (TDD, branch, gates, contrato) está na
[receita de fatia vertical](vertical-slice-recipe.md); aqui está o **mapa**.
Padrões e vereditos vivem em [06-padroes](06-padroes.md).

Checklist comum a todas: fatia mínima observável → teste focado que falha →
menor implementação → `composer lint`, `composer analyse`, suíte focada →
snapshot do contrato se rota/resposta mudou → cobertura segue 100% no merge
pgsql+sqlite (nada de `@codeCoverageIgnore`).

---

## A. CRUD administrativo de recurso novo

Exemplo ilustrativo: recurso `Department` (espelha `Users`).

| # | Arquivo | Papel |
| --- | --- | --- |
| 1 | `database/migrations/*_create_departments_table.php` | tabela + índices; guard por driver se SQL específico |
| 2 | `app/Models/Department.php` | model fino; casts e `$fillable` |
| 3 | `app/Enums/Permission.php` + `RbacCatalogueSeeder` | **permissão nova exige mudança de código**: catálogo é fechado, sem CRUD HTTP |
| 4 | `app/Policies/DepartmentPolicy.php` + registro no provider | regras por objeto (anti-autoaplicação, invariantes) |
| 5 | `app/Http/Requests/Departments/*Request.php` | validação + `authorize()` com o gate |
| 6 | `app/Actions/Departments/*.php` | mutação em `PrivilegeTransaction` + `RecordsPrivilegeAudit` |
| 7 | `app/Http/Controllers/Departments/DepartmentController.php` | fino: request → action → resource |
| 8 | `app/Http/Resources/Departments/DepartmentResource.php` | só campos permitidos; dado sensível atrás de policy |
| 9 | `routes/api/departments.php` + require em `routes/api.php` | `auth:sanctum`, gate `can:`, throttle |
| 10 | `tests/Feature/...` | HTTP com `ActsAsSanctumUser`; mutation test cobre policy + auditoria + rollback |

**Armadilhas.** Auditoria fail-closed: `Audit::record` com `action` da allowlist na
mesma transação, ou a mutação reverte. Listagem: filtro novo entra em
`app/Support/Filters` com allowlist + guard de driver, coberto em `FiltersTest`.
Resposta mudou → snapshot candidato do contrato no mesmo PR.

## B. Campo novo em resource existente

1. Migration (nulável quando possível; backfill em seeder/comando, não no deploy).
2. `$fillable` + cast no model; regra de validação no(s) Request(s).
3. Exposição no Resource — se sensível, atrás de policy como o CPF
   (`$this->when($can, ...)`), nunca `hidden` como único controle.
4. Teste: com e sem permissão; contrato: campo aditivo sob `/api/v1` é compatível,
   remoção/renomeação exige `/api/v2` + entrada em
   `contracts/accepted-breaking-changes.json`.

## C. Filtro novo em listagem

1. Chave allowlisted em `app/Support/Filters/XFilters.php`; desconhecidas são
   ignoradas, nunca erro.
2. `ilike`/`like` sempre com `ESCAPE` e guard por driver (padrão de `RoleFilters`).
3. Cobertura nos dois drivers: `%` e `_` literais, caixa alta, chave desconhecida —
   espelhe `tests/Feature/Support/FiltersTest.php`.

## D. Notificação ou job assíncrono

1. Notification com `ShouldQueue` + `tries`/`backoff`; disparo no model via
   `->afterCommit()` (padrão de `User::sendEmailVerificationNotification`).
2. Teste no molde de `AfterCommitDispatchTest`: fila isolada, `after_commit=false`,
   rollback enfileira 0, commit enfileira N.
3. Efeitos do job idempotentes (retries acontecem); `QUEUE_AFTER_COMMIT=true`
   preservado em toda conexão; worker timeout abaixo de `retry_after`.
4. E-mail novo: assunto/greeting/action como `VerifyEmail`; link com expiração
   curta; nunca segredo em texto.

## E. Integração externa (e-mail, webhook, API terceira)

1. Adapter em `app/Support` ou `app/Services`: traduz erros/payloads da API
   externa para o vocabulário interno (`ApiMessage`); controller nunca fala HTTP
   externo.
2. Credenciais em `config/*.php` via env, nunca hardcoded; timeout e retry
   explícitos.
3. Teste com fake na borda (Mail/HTTP fake), nunca chamada real; efeito colateral
   fora de transação ou com `afterCommit`.

## F. Permissão nova no catálogo

1. Valor em `app/Enums/Permission.php` → `RbacCatalogueSeeder` sincroniza
   (idempotente, conjunto exato) → gate registrado → policy usa.
2. Papel `admin` recebe tudo; `user` recebe nada por padrão. Seeders nunca atribuem
   papel a conta.
3. É mudança de código revisada por desenho (ADR 0008): documente o motivo no PR.

---

## 🗺️ Como partir, em 5 passos

1. Descreva a fatia em uma frase observável ("admin com `departments.manage` cria
   department e vê na listagem") e liste autorização, estados e erros.
2. Comece pelo teste HTTP com `ActsAsSanctumUser` (vermelho primeiro).
3. Implemente de dentro para fora: migration → policy/action → request →
   controller → resource → rota.
4. Rode a perna sqlite rápida; confira o contrato se a resposta mudou.
5. Revise o diff: só a fatia, sem camada "para o futuro" ([06-padroes](06-padroes.md)
   diz o que não construir).
