# Aprovação humana — FEAT-1

Aprovador e owner das decisões: yanpenalva. Data: 2026-10-01. Evidência: respostas nesta conversa e instrução explícita “PLEASE IMPLEMENT THIS PLAN: Consolidar as decisões dos ADRs”. Não há URL pública da conversa; este registro preserva as decisões sem inventar identificador externo.

## Decisões confirmadas

- ADRs 0001–0003: API Laravel e SPA Vue/Quasar independentes; sessão/cookie/CSRF via Sanctum; PostgreSQL, Redis sob demanda e OpenAPI. Versões resolvidas e ambiente ficam para suas entregas.
- ADR 0004: cadastro público, ativação automática após verificação de email, CPF opcional no perfil para identificação cadastral, normalizado em 11 dígitos, formato/dígitos verificadores válidos e único quando informado. Consulta do titular e gestão com permissão específica. Primeiro administrador escolhido explicitamente e promovido por comando restrito após verificação.
- ADR 0005: Activitylog v5, 90 dias de retenção de eventos, consulta por permissão específica, sem edição/exclusão pela API; falha de gravação reverte alteração de privilégios/bootstrap. CPF e credenciais fora da trilha; eventos/diffs somente permitidos explicitamente.
- ADR 0006: excluir LDAP, criar textos/regras pelos novos requisitos e tratar CPF como decisão própria da nova API.

## Escopo autorizado

Atualizar seis ADRs e contexto canônico, registrar aprovação, sincronizar FEAT-1 e executar validação estrita, testes existentes e doctor CSD. Substituir ativação administrativa pela automática. A aprovação abrange arquitetura e execução documental; não equivale a revisão independente de código runtime.

## Limites

Não criar endpoints, migrations ou instalações de aplicação. Não realizar commit, push, PR, deploy ou encerramento da issue. Domínios, infraestrutura, versões resolvidas, permission names e primeira feature ficam para entregas futuras. Retenção em backups será especificada antes da coleta em produção. Operadores e requisitos operacionais não são inferidos a partir do aprovador arquitetural.

## Incorporação posterior aprovada

O usuário aprovou explicitamente o plano “Incorporar o ADR de legado e preparar o scaffold da API”: incorporar decisões de 0006 em 0001/0002/0004, retirar documento separado e preservar identificador histórico. O registro original acima é mantido. Ativação automática prevalece sobre texto desatualizado da issue #14. Scaffold/Compose/CI pertencem a FEAT-7/8/24, sem Git finalizado ou mudanças externas.
