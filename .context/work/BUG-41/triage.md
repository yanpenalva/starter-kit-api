# Support Triage — BUG-41 Serializar mapas vazios de nomes de papéis como objeto

## Classification

- Type: bug
- Reporter: yanpenalva
- Triage owner: yanpenalva
- First observed: 2026-10-13

## Impact

- Affected users or systems: consumidores dos endpoints de perfil e usuários.
- Current impact: resposta vazia viola o schema OpenAPI para `role_display_names`.
- Scope and frequency: perfis não verificados e usuários sem papéis.
- Severity: sev4

## Immediate decision

- Containment required: no
- Escalation required: no
- Chosen path and reason: bug local e reproduzível, corrigido mantendo o contrato existente.
