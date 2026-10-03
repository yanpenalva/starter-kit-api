# Specification — FEAT-9 (primeira fatia, wave 1)

## Scope

Migration + Model + Factory do User: identidade, estado de verificação vs. desativação explícita independentes, CPF opcional, schema de reset tokens conforme convenções Laravel. Testes de constraint em PostgreSQL isolado (perfil `test` do compose).

## Decisões da fatia

- Verificação de email (`email_verified_at`) e desativação explícita (`deactivated_at`) são campos independentes; um não infere o outro.
- CPF: string de exatamente 11 dígitos (normalização preserva zeros à esquerda), rejeição por checksum e por dígitos repetidos; coluna `nullable` com constraint UNIQUE parcial/índice único — múltiplos NULL válidos no PostgreSQL.
- Reset tokens: tabela padrão Laravel (`password_reset_tokens`) com email, token, `created_at`; sem invenção além das convenções.
- Mass assignment: `$fillable` limitado aos campos de identidade; `$hidden` com `password`/`remember_token`/tokens; campos privilegiados (roles/permissions) nem existem nesta fatia.
- Sessions: mantém `file` (dev) / `array` (teste) — nenhum driver escolhido exige tabela `sessions`; escolha real registrada aqui, sem tabela especulativa.
- Sem RBAC/audit/seeders de privilégio, sem admin real/produção, sem DTO/perfil público antes da wave 3, sem comando destrutivo sobre dados de dev.

## Out of scope

Verificação de email em runtime, cadastro público (#14), login (#13), RBAC/audit (#16/#19), produção (#25/#26).

## Acceptance

- Migrations rodam em PostgreSQL 18 isolado (perfil test); constraints testadas: CPF ausente ok, inválido/repetido rejeitado pela regra de validação, duplicado rejeitado pelo banco, múltiplos NULL ok, concorrência de insert duplicado tratada onde fizer sentido.
- Factory gera User válido (com/sem CPF, verificado/não verificado, ativo/desativado).
- Pint + Larastan nível 10 sem baseline; strict types; sem `else` além do permitido pela política; sem supressão de findings.
- Issue #9 permanece aberta (fatias RBAC/audit/seeders vêm nas waves 2+).

## Entrega

Branch `feat/wave-1-api-foundations` (base 398a952, criada pelo pai), alvo `main`, remote `origin`; commit/push/PR sob [EXC-0001](../../exceptions/EXC-0001.json).
