# Specification — FEAT-19 (primeira fatia, wave 2 — somente escrita)

Pacote aprovado: `spatie/laravel-activitylog` **5.1.1** (PHP ^8.4, Laravel 12/13). Sem feed/leitura nesta fatia (wave 6); retenção 90 dias configurada, cleanup agendado só na wave 6.

## Eventos e envelope (aprovado)

- Eventos: `cli.bootstrap`, `privileged.change` — nada além.
- Envelope mínimo: IDs de ator/alvo, `correlation_id` (propriedade custom), timestamps. `attribute_changes` vazio (causado pelo novo modelo 5.1.1); custom properties SEM CPF/email/credenciais/tokens/payload integral; SEM auto-log de User (nenhum `LogsActivity` concern no User; `Support\LogOptions`/`Models\Concerns\LogsActivity` namespaces oficiais 5.1.1 quando usados em modelos não-User futuros).

## Garantias de escrita (fail-closed)

- Log síncrono em DB, buffer/desativado (`submit_logs_batch` OFF); cada mutation privilegiada + seu(s) registro(s) de auditoria na MESMA transação PostgreSQL; falha de gravação reverte a operação (rollback atômico).
- Migration coordenada `*_000003_activity_log` (schema oficial 5.1.1) — dono: FEAT-19; `*_000004` é da lane async.

## Out of scope

Feed/leitura/permissão `logs.view` em runtime (wave 6), scheduler de retenção, difs por allowlist além do envelope aprovado, auto-log genérico.

## Entrega

Branch `feat/wave-2-identity-audit` → `main` @origin; [EXC-0001](../../exceptions/EXC-0001.json). Testes: falha de audit reverte mutation; envelope sem campos sensíveis (CPF/email/creds/tokens) mesmo com valores presentes; transação atômica em PG isolado.
