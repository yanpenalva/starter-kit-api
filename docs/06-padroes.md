# 🧱 Padrões: o que usamos, onde vive e o que evitamos

Este guia cataloga os design patterns da API no formato onde vive → ganho →
limite. É a leitura para antes de propor uma camada nova: quase tudo aqui já
existe e o ganho está em preservar fronteiras, não em adicionar abstração.
Exemplos são trechos do código real, não implementações completas.

## 📑 Índice

1. [Mapa GoF → código](#-mapa-gof--código)
2. [Action / Command — existe](#-action--command--existe)
3. [FormRequest / Resource — existe; DTO, não agora](#-formrequest--resource--existe-dto-não-agora)
4. [Policy / Gate — existe](#-policy--gate--existe)
5. [Events — existe; Observer de negócio, não](#-events--existe-observer-de-negócio-não)
6. [Strategy — variação encapsulada, sem hierarquia](#-strategy--variação-encapsulada-sem-hierarquia)
7. [Factory / Builder — existe](#-factory--builder--existe)
8. [Middleware pipeline — existe](#-middleware-pipeline--existe)
9. [Adapter / Command CLI — existe](#-adapter--command-cli--existe)
10. [Query Object / Specification — filtros existem; Specification genérica, não](#-query-object--specification--filtros-existem-specification-genérica-não)
11. [Despacho pós-commit — existe](#-despacho-pós-commit--existe)
12. [Explicitamente não](#-explicitamente-não)
13. [Escalabilidade e performance: gatilhos, não previsões](#-escalabilidade-e-performance-gatilhos-não-previsões)

---

## 🗺️ Mapa GoF → código

| GoF (família) | Neste código | Onde vive |
| --- | --- | --- |
| Command (comportamental) | Action com `run()` | `app/Actions/Users`, `app/Actions/Roles` |
| Strategy (comportamental) | Variação por driver com guard, sem hierarquia | `app/Support/EmailLookup.php`, `app/Support/Filters/*`, `app/Support/PrivilegeTransaction.php` |
| Chain of Responsibility (comportamental) | Pipeline de middleware | `app/Http/Middleware/*`, `bootstrap/app.php` |
| Observer (comportamental) | Events do framework | `VerifyEmailController` dispara `Verified`; `ResetPasswordController` dispara `PasswordReset` |
| Factory / Builder (criação) | Factories, seeders, Eloquent Builder | `database/factories/UserFactory.php`, `database/seeders/RbacCatalogueSeeder.php` |
| Adapter (estrutural) | CLI → caso de uso; Mail → transporte | `app/Console/Commands/PromoteAdmin.php` → `BootstrapAdmin`; `Mail::queue(new SyntheticInfraMail(...))` |
| Decorator (estrutural) | API Resources sobre models | `app/Http/Resources/*` (`UserResource` condiciona o CPF à permissão) |
| Specification (não-GoF, Evans) | Query Objects com allowlist | `app/Support/Filters/{UserFilters,RoleFilters,ActivityFilters}.php` |

Policy/Gate é autorização (Acerbis/CAT), não pattern GoF: concentra "este ator pode
isto neste objeto?" em `app/Policies/*`, registrado no `AppServiceProvider`.

---

## ⚔️ Action / Command — existe

**Onde vive:** `app/Actions/Users/StoreUser.php`, `AssignUserRoles.php`,
`app/Services/TotpService.php`, `MfaRecoveryCodeService.php`.

```php
return new UserResource(
    $this->assignUserRoles->run($actor, $user, $request->roleNames())
);
```

**Ganho.** Cada Action é um caso de uso nomeado, testável sem HTTP. O framework é o
invoker (o container injeta a Action no controller); `run()` é o execute. Actions
compõem (`StoreUser` chama `AssignUserRoles`).

**Limite.** Sem `UserService` gordo por entidade, sem interface por Action (interface
sem 2+ implementações é formalismo, não desacoplamento), sem Action que só repassa
para outro Service. `RegisterController` ainda orquestra criação + papel inicial:
extrair só quando houver reuso ou complexidade adicional.

---

## 📥 FormRequest / Resource — existe; DTO, não agora

**Onde vive:** `StoreUserRequest` (valida + normaliza e-mail via `EmailLookup`),
`UserController::store()`, `UserResource` (CPF só com `readCpf`).

**Ganho.** FormRequest é validação de borda HTTP; Action executa; Resource controla
o contrato público. Três responsabilidades, três lugares.

**Limite.** DTO não substitui nenhum dos dois. Parâmetros explícitos e arrays com
shape documentado bastam hoje. DTO imutável só quando HTTP, CLI e Jobs
compartilharem uma estrutura complexa — e validação de entrada não-HTTP continua
obrigatória (DTO tipado não valida dado externo).

---

## 🛡️ Policy / Gate — existe

**Onde vive:** `app/Policies/UserPolicy.php`, `RolePolicy.php`; gates por permissão
do enum no `AppServiceProvider`.

```php
$this->authorize('assignRoles', [$user, $request->roleNames()]);
```

**Ganho.** Nada de `hasRole('admin')` espalhado. Checks internos nas Actions não são
duplicação: autorização pré-transação pode ficar stale, por isso `AssignUserRoles`
relê e reautoriza dentro de `PrivilegeTransaction`.

**Limite.** Policy não substitui lock, constraint de banco nem autenticação Sanctum.

---

## 📣 Events — existe; Observer de negócio, não

**Onde vive:** `VerifyEmailController` dispara `Verified`; `ResetPasswordController`
dispara `PasswordReset`.

**Ganho.** Listeners adicionam efeitos independentes sem acoplar o fluxo principal.

**Limite.** Autorização, atribuição de papéis e auditoria obrigatória nunca vão para
Observer: `Audit::record()` participa da transação para que a mutação não sobreviva
sem auditoria. Observer só para comportamento universal de model — e updates em
massa sequer disparam eventos individuais.

---

## 🔀 Strategy — variação encapsulada, sem hierarquia

**Onde vive:** `EmailLookup::whereNormalized()`, `UserFilters`, `RoleFilters`,
`PrivilegeTransaction` (lock só no pgsql).

```php
if (DB::connection()->getDriverName() === 'pgsql') {
    return $query->whereRaw("lower(btrim(email, E' \\t\\n\\r\\x0B')) = ?", [$normalized]);
}
return $query->whereRaw('lower(trim(email)) = ?', [$normalized]);
```

**Ganho.** Um ponto controla a diferença SQL; `ilike` sempre atrás de guard
explícito. Isso é seleção condicional, não hierarquia Strategy GoF — e está certo
assim. Classes por driver só aumentariam navegação.

**Limite.** SQLite não prova equivalência de Unicode, locks ou unicidade no
PostgreSQL: preserve testes nas duas pernas.

---

## 🏭 Factory / Builder — existe

**Onde vive:** `UserFactory` (`verified()`, `withCpf()`), `RbacCatalogueSeeder`,
`ContractResponseFactory` (Scramble), Eloquent Builder nas consultas.

```php
$user = User::factory()->verified()->withCpf()->create();
```

**Ganho.** Estados compõem fixtures consistentes; seeders mantêm o catálogo sem
criar admins nem atribuir papéis.

**Limite.** Sem Builder próprio para `User`, sem AbstractFactory de models:
duplicariam APIs que o framework já dá.

---

## 🔗 Middleware pipeline — existe

**Onde vive:** `bootstrap/app.php` (`prepend(CorrelateRequest::class)`,
`append(ApplyApiSecurityHeaders::class)`), `routes/api/users.php`.

**Ganho.** Responsabilidade transversal envolve a execução sem contaminar Actions.
Ordem é segurança: bloqueio de `Authorization` antes da autenticação, sessão/CSRF,
desafio MFA, headers também nas respostas de erro.

**Limite.** Sem pipeline paralelo próprio nem decorators de controller.
`Correlation::$current` é estático: antes de workers persistentes, revisar
isolamento entre requisições e Jobs.

---

## 🔌 Adapter / Command CLI — existe

**Onde vive:** `PromoteAdmin` adapta argv → `BootstrapAdmin::run()`; comandos de
infra usam `config/mail.php` sem conhecer transporte.

**Ganho.** CLI traduz argumento/resultado; Mail/Notifications deixam transporte
com o framework. Sem `MailServiceInterface` embrulhando uma chamada.

**Limite.** Adapter próprio só diante de API externa com semântica diferente
(erros, payloads, idempotência). Sem Command Bus/CQRS para despachar Actions.

---

## 🔍 Query Object / Specification — filtros existem; Specification genérica, não

**Onde vive:** `UserFilters::apply(User::query()->with('roles'), $filters)
->orderBy('id')->paginate(...)` em `IndexUsers`.

**Ganho.** Composição SQL testável sem Repository; eager loading explícito evita
N+1 de papéis na listagem.

**Limite.** Scopes para regras reutilizadas; Specification só com composição real
de regras de negócio — nunca uma classe por parâmetro HTTP.

---

## 📬 Despacho pós-commit — existe

**Onde vive:** `User::sendEmailVerificationNotification()` e
`sendPasswordResetNotification()` usam `->afterCommit()`; `VerifyEmail` e
`ResetPassword` implementam `ShouldQueue`.

**Ganho.** Protege chamadas dentro de transações externas independente da config
da conexão (`SendQueuedNotifications` propaga o `afterCommit` da notification para
o job). Fora de transação, o comportamento é idêntico.

**Limite.** Pós-commit não é entrega atômica: Outbox só quando perder um evento
entre commit e enqueue for inaceitável. Coberto por `AfterCommitDispatchTest`
(rollback enfileira 0, commit enfileira 2, com `after_commit=false`).

---

## 🚫 Explicitamente não

| Pattern | Por que não aqui |
| --- | --- |
| Repository genérico sobre Eloquent | Esconderia o query builder e criaria segunda API pior: sem eager loading explícito, sem scopes, sem paginação nativa. `IndexUsers` mantém `User::query()`. |
| AbstractFactory especulativa | Sem famílias intercambiáveis; `User::factory()->verified()` basta. |
| Singleton manual | Resolução pelo container (`app(UserPolicy::class)`); global própria complica testes e vaza estado em workers. |
| Command Bus / CQRS framework | Artisan commands e Actions não precisam de barramento; CQRS leve (queries separadas de mutações) já existe sem framework. |
| DTO prematuro | Ver seção FormRequest acima. |
| Microserviços / event sourcing | `Users`, `Roles` e auth já são fronteiras; agrupar por módulo só com time/responsabilidade que justifique. |

---

## 📈 Escalabilidade e performance: gatilhos, não previsões

Nada abaixo é tarefa aberta: cada item tem um **gatilho mensurável**. Sem medição,
não entra.

| Item | Corte quando medir dor | Como medir |
| --- | --- | --- |
| `count` da paginação | `simplePaginate` se o contrato dispensar totais; `cursorPaginate` (id já é ordenação única) para navegação profunda | duração do `count` vs página; p95 em páginas profundas; `EXPLAIN (ANALYZE, BUFFERS)` |
| Busca `%termo%` | índice `pg_trgm` no pgsql; intervalo direto em `created_at` no audit feed | planos, buffers, p95; custo de escrita do índice |
| Advisory lock global (`727423`) | encurtar a seção crítica; nunca lock por usuário sem preservar `otherAdminsRemain()` | espera em `pg_locks`/`pg_stat_activity`, throughput concorrente (teto ≈ 1/T) |
| Invalidação RBAC | reduzir invalidações redundantes por transação, mantendo pós-commit | invalidações, misses e queries por mutação |
| TOTP multi-instância | `CACHE_STORE=redis` (ou database compartilhado); `array`/arquivo não sustenta anti-replay distribuído | latência do `add`; exatamente 1 aceite por scope/slice concorrente |
| Filas | `QUEUE_CONNECTION=redis` sob pressão; preservar `QUEUE_AFTER_COMMIT=true` | SQL de filas, atraso, jobs/s |
| Hidratação Eloquent | `toBase()->lazyById()` em export futuro; agregação no banco; read model só se custo recorrente justificar | CPU PHP, pico de memória, tempo SQL, p95 |

Abandonar o Eloquent nos reads resolve CPU/memória de hidratação em volume — e só
isso. Não resolve `count`, offset, índice ausente, lock ou invalidação; e cobra
projeção própria, allowlist e manutenção pgsql/sqlite em duplicata.
