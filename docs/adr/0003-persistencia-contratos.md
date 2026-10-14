# ADR 0003 — Persistência e contratos mínimos

- Estado: aceito; decisão arquitetural aprovada, ainda não implementada.
- Data: 2026-10-01. Owner e aprovador: yanpenalva. Evidência: revisão nesta conversa e solicitação explícita de implementar o plano “Consolidar as decisões dos ADRs”; registro em FEAT-1.

## Contexto e evidência

A [issue #1](https://github.com/yanpenalva/starter-kit-api/issues/1) e a [stack](../../.context/project/stack.md) registram PHP 8.5, Laravel 13, PostgreSQL, Redis quando necessário e OpenAPI/Scramble como direção de integração. Na aprovação inicial não existia manifesto/lockfile; FEAT-7 estabelece Composer e lockfile no scaffold PHP 8.5.

## Decisão

Usar PostgreSQL como banco da aplicação. Introduzir Redis somente por necessidade demonstrada de cache/filas, sem torná-lo requisito do scaffold. Fixar dependências resolvidas em `composer.lock`; verificar compatibilidade real antes de instalar e atualizar por mudanças revisadas. Registrar versões de PHP, banco e serviços no ambiente reproduzível do scaffold.

Documentar contratos HTTP em OpenAPI na primeira entrega de endpoints: entradas, respostas, erros e autenticação. Scramble é a ferramenta planejada; sua versão e compatibilidade permanecem pendentes de resolução. Gerador TypeScript será decidido com o frontend ao existir contrato consumível.

## Alternativas

SQLite como banco de produção, Redis obrigatório e versões flutuantes divergem da direção registrada ou acrescentam dependências sem evidência. Testes que dependem de semântica PostgreSQL deverão usar PostgreSQL.

## Impactos e pendências

Versão PostgreSQL/Redis, migrations, schema de negócio, images e gerador de tipos: NOT FOUND. Nenhuma tabela ou versão de pacote é criada por este ADR. Antes da primeira mudança de dados, definir rollback e testar a migração. Validar o contrato antes de entregá-lo ao frontend.
