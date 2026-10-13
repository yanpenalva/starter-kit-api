# ADR 0009 — Jornada registro-acesso e ativação condicionada por motivo de bloqueio

- Estado: aceito
- Substitui parcialmente: [ADR 0004](0004-identidade-rbac.md) (ativação após verificação e proibição de reativação por verificação)
- Data: 2026-10-12

## Contexto

No registro público de novos usuários, a conta é criada com `deactivated_at` definido e `email_verified_at` nulo, categorizada com o motivo `pending_approval` em `BlockedAccount`. Anteriormente, a verificação de e-mail nunca limpava o campo `deactivated_at`, impedindo que usuários recém-cadastrados concluíssem a jornada para obter acesso básico ao sistema.

## Decisão

A verificação de e-mail limpa `deactivated_at` (definindo-o como `null`) SOMENTE se o motivo de bloqueio da conta for `pending_approval`. Contas desativadas administrativamente após verificação prévia (`suspended`, onde `deactivated_at > email_verified_at`) permanecem desativadas mesmo se uma verificação de e-mail for executada, preservando a autoridade da moderação administrativa.

## Consequências

- Novos registros completam a jornada de ativação de forma autônoma após verificação bem-sucedida do endereço de e-mail.
- Contas suspensas não recuperam acesso através de fluxos de verificação de e-mail.
- Contas fora de `pending_approval` (`suspended`) não são alteradas por `markEmailAsVerified()`: o método retorna `false` sem gravar nada.

## Desempate de timestamps e limitações

- O motivo é derivado apenas de `email_verified_at` e `deactivated_at`, sem coluna nova. Se `deactivated_at` for igual a `email_verified_at` (empate), o motivo resolve para `pending_approval`; só `deactivated_at` estritamente posterior a `email_verified_at` é `suspended`.
- Limitação: uma suspensão aplicada no mesmo instante da verificação (mesma resolução de timestamp) é lida como `pending_approval` e a conta seria ativada ao verificar. Como a verificação só ocorre quando `email_verified_at` é nulo, o risco fica restrito a esse caso de borda.
- Limitação: a distinção depende de relógios consistentes na aplicação e no banco; não há trilha própria do motivo além da auditoria (ADR 0005).
- Limitação: conta nunca verificada e suspensa administrativamente (`email_verified_at` nulo) também é lida como `pending_approval` e é ativada ao verificar o e-mail.
