# Plan — BUG-41 Serializar mapas vazios de nomes de papéis como objeto

## Approved approach

Converter `array_combine` para `stdClass` em ambos os resources. Assim o serializador preserva o objeto inclusive sem propriedades e o snapshot OpenAPI continua válido.

## Confirmed touchpoints

- Files/components/services: dois resources Auth/Users e testes de perfil/admin.
- Interfaces and data: nenhuma alteração de contrato.
- Dependencies: nenhuma.

## Execution order

1. Adicionar assert de objeto, observar falha, corrigir resources e executar gates solicitados.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| BUG-41-1 | Codex | none | Testes de perfil e UserResource confirmam objeto vazio; lint/analyse aprovados | 1 | core,project,testing |

Integração: Codex; handoff paralelo: não aplicável.

## Test plan

- PHPUnit SQLite para perfil, usuários, papéis e contrato.
- `composer lint` e `composer analyse`.

## Risks and rollback

- Risk: consumidor PHP interno poderia esperar array no payload resolvido.
- Mitigation: o campo representa mapa JSON e o contrato vigente exige objeto.
- Rollback: reverter o commit único.

## Required approvals

- Nenhuma aprovação adicional; usuário solicitou commit e push nesta branch.
