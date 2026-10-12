# ADR 0009 — Jornada registro-acesso e ativação condicionada por motivo de bloqueio

- Estado: aceito
- Sucessor de: [ADR 0004](0004-identidade-rbac.md)
- Data: 2026-10-12

## Contexto

No registro público de novos usuários, a conta é criada com `deactivated_at` definido e `email_verified_at` nulo, categorizada com o motivo `pending_approval` em `BlockedAccount`. Anteriormente, a verificação de e-mail nunca limpava o campo `deactivated_at`, impedindo que usuários recém-cadastrados concluíssem a jornada para obter acesso básico ao sistema.

## Decisão

A verificação de e-mail limpa `deactivated_at` (definindo-o como `null`) SOMENTE se o motivo de bloqueio da conta for `pending_approval`. Contas desativadas administrativamente após verificação prévia (`suspended`, onde `deactivated_at > email_verified_at`) permanecem desativadas mesmo se uma verificação de e-mail for executada, preservando a autoridade da moderação administrativa.

## Consequências

- Novos registros completam a jornada de ativação de forma autônoma após verificação bem-sucedida do endereço de e-mail.
- Contas suspensas não recuperam acesso através de fluxos de verificação de e-mail.
