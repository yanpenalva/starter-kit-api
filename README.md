# starter-kit-api

API REST independente do ecossistema Starter Kit, construída com Laravel 13 sobre PHP 8.5. Expõe autenticação por sessão via Sanctum com CSRF, gestão de usuários e papéis com RBAC, trilha de auditoria e contrato OpenAPI versionado, tudo sob o prefixo `/api/v1`.

## Índice

1. [Visão geral](#1-visão-geral)
2. [Stack tecnológica](#2-stack-tecnológica)
3. [Arquitetura](#3-arquitetura)
4. [Instalação](#4-instalação)
5. [Funcionalidades](#5-funcionalidades)
6. [Autenticação, sessão e segurança](#6-autenticação-sessão-e-segurança)
7. [Filas, jobs e agendamento](#7-filas-jobs-e-agendamento)
8. [Arquitetura de código](#8-arquitetura-de-código)
9. [Documentação da API com OpenAPI versionado](#9-documentação-da-api-com-openapi-versionado)
10. [Docker e perfil release](#10-docker-e-perfil-release)
11. [Testes e qualidade](#11-testes-e-qualidade)
12. [Integração fullstack](#12-integração-fullstack)
13. [Boas práticas](#13-boas-práticas)
14. [Convenções de commit](#14-convenções-de-commit)
15. [Padrões de código](#15-padrões-de-código)

## 1. Visão geral

A `starter-kit-api` é o backend do ecossistema Starter Kit. É uma API stateless do ponto de vista de tokens: não distribui tokens de acesso por cabeçalho. Toda autenticação de primeira parte acontece por sessão em cookie, com proteção CSRF, destinada ao consumo pelo SPA próprio.

O roteamento da API vive em `routes/api.php` com prefixo `api/v1`, configurado em `bootstrap/app.php`. A raiz do versionamento responde o estado da API:

```sh
curl --fail http://localhost:8080/api/v1
```

```json
{"status":"ok"}
```

A prontidão operacional é exposta publicamente em `GET /api/v1/health`, que retorna HTTP 200 com o estado de cada dependência quando tudo está saudável, e HTTP 503 com o detalhe por verificação quando algo falha. O endpoint `/up` do próprio framework também existe e indica apenas que a aplicação inicializou, sem verificar dependências.

Decisões de arquitetura estão registradas como ADRs incrementais em `docs/adr/`. A operação de release, deploy e rollback está documentada em `docs/release.md`, o guia de desenvolvimento local em `docs/onboarding.md` e as variáveis obrigatórias de produção em `docs/security-configuration.md`.

## 2. Stack tecnológica

| Camada | Tecnologia | Versão / detalhe |
| --- | --- | --- |
| Linguagem | PHP | 8.5 (`php:8.5-fpm-bookworm` no Docker) |
| Framework | Laravel | 13.17 (`laravel/framework ^13.17`) |
| Autenticação | Sanctum stateful | `laravel/sanctum ^4.3.3`, sessão em cookie mais CSRF |
| Autorização | Spatie Permission | `spatie/laravel-permission ^8.3.0` |
| Auditoria | Spatie Activitylog | `spatie/laravel-activitylog ^5.1.1` |
| Documentação da API | Scramble | `dedoc/scramble 0.13.47`, OpenAPI 3.1.0 |
| Banco de dados | PostgreSQL | 18 (`postgres:18-alpine`) |
| Fila | Banco de dados | driver `database` com `after_commit` habilitado |
| Cache e sessões em release | Redis | `redis:alpine` com extensão `redis-6.3.0` |
| E-mail local | Mailpit | `axllent/mailpit`, SMTP em `mailpit:1025` |
| Servidor HTTP local | Nginx | `nginx:1.29-alpine` como proxy reverso para o PHP-FPM |
| Testes | Pest | `pestphp/pest ^4.0` com plugin Laravel |
| Formatação | Pint | `laravel/pint ^1.27`, preset `laravel` |
| Análise estática | PHPStan via Larastan | `larastan/larastan ^3.0`, nível 10 |
| Cobertura | pcov | gate de 85% no CI |
| Hash de senhas | argon2id | driver padrão em `config/hashing.php` |

Requisitos de ambiente local: Linux, Docker Engine com Docker Compose v2, Python 3 e acesso de rede para baixar imagens e pacotes. O PHP 8.5 e o Composer executam dentro dos containers; o PHP do host não é utilizado.

## 3. Arquitetura

A aplicação segue a organização padrão do Laravel, com autenticação stateful servida pelo middleware `statefulApi` do framework e reforçada por dois middlewares próprios aplicados antes da autenticação: `DisallowHeaderAuthentication`, que rejeita credenciais por cabeçalho, e `EnsureStatefulSession`, que exige sessão presente nas rotas de login e registro.

O fluxo de autenticação usa o guard `web` do Sanctum. O cookie de sessão identifica o navegador, o middleware de CSRF valida o token em mutações e o `AuthenticateSession` do Sanctum invalida sessões em outros dispositivos quando a senha muda. A expiração padrão da sessão é de 120 minutos.

A autorização combina duas camadas. O catálogo de permissões é um enum (`App\Enums\Permission`) e cada permissão do catálogo é registrada como um gate no `AppServiceProvider`: o gate exige e-mail verificado, conta ativa e a permissão concedida via Spatie. As regras por objeto ficam nas policies: `UserPolicy` protege o próprio usuário, o último administrador e transições de estado, enquanto `RolePolicy` protege o papel `admin`, que é imutável.

A auditoria usa o Spatie Activitylog. Mutações privilegiadas gravam o evento na mesma transação da operação: se a gravação do evento falha, a mutação é revertida. A trilha não pode ser editada nem removida pela API, a leitura exige permissão específica e a limpeza automática é restrita ao processo operacional.

Os contratos HTTP são documentados em OpenAPI gerado pelo Scramble e publicados como snapshot versionado em `contracts/openapi.json`. O repositório frontend gera seus tipos TypeScript a partir desse contrato.

O banco de desenvolvimento é persistente em volume dedicado. O banco de testes usa armazenamento efêmero e credenciais próprias, em rede isolada, de modo que a suíte nunca alcance dados de desenvolvimento.

## 4. Instalação

A partir de um checkout limpo, na raiz do repositório:

```sh
python3 scripts/setup_local_env.py
docker compose build api
docker compose run --rm --no-deps api composer install --no-interaction --prefer-dist
docker compose run --rm --no-deps api php artisan key:generate --no-interaction
docker compose up -d --wait
curl --fail http://localhost:8080/api/v1
curl --fail http://localhost:8080/up
```

O script `scripts/setup_local_env.py` cria o `.env` uma única vez, com senhas locais aleatórias distintas para desenvolvimento e teste e com o UID/GID do usuário Linux atual. Ele se recusa a sobrescrever uma configuração existente. Nenhum segredo é impresso ou versionado.

Defina `HTTP_PORT` no `.env` para trocar a porta HTTP padrão 8080. O `APP_DEBUG` permanece `false` mesmo em desenvolvimento. O PHP-FPM usa o mesmo UID/GID do checkout para que os arquivos gerados continuem pertencendo ao desenvolvedor.

Para aplicar migrations no banco de desenvolvimento:

```sh
docker compose exec api php artisan migrate --no-interaction
```

Esse comando atinge somente o desenvolvimento. Nunca use `migrate:fresh` no banco de desenvolvimento.

Em caso de erro de autenticação no banco, compare o `.env` local com as credenciais usadas quando o volume foi inicializado; não redefina o volume silenciosamente. Em caso de erro de permissão, regenere apenas a configuração de UID/GID, sem trocar segredos ou dados existentes. O comando `docker compose down` preserva o volume de desenvolvimento; não use `down --volumes` como reinicialização de rotina.

Se o checkout dividir a máquina com outros checkouts do mesmo repositório, sempre informe um nome de projeto único, porque o `compose.yaml` fixa o nome compartilhado `starter-kit-api`:

```sh
docker compose -p meu-checkout-local up -d --wait
```

Nunca execute comandos destrutivos como `down --volumes` contra o nome compartilhado.

## 5. Funcionalidades

Todas as rotas abaixo estão sob o prefixo `/api/v1`.

| Área | Endpoints | Acesso |
| --- | --- | --- |
| Estado da API | `GET /` | público |
| Prontidão | `GET /health` | público |
| Registro | `POST /register` | público, com sessão stateful e throttle |
| Login | `POST /login` | público, com sessão stateful e throttle |
| Recuperação de senha | `POST /forgot-password`, `POST /reset-password` | público, com throttle |
| Verificação de e-mail | `GET /email/verify/{id}/{hash}`, `POST /email/verification-notification` | URL assinada mais throttle; reenvio autenticado |
| Perfil próprio | `GET /auth/my-profile` | autenticado, conta ativa |
| Logout | `POST /logout` | autenticado |
| Usuários | `POST /users`, `GET /users`, `GET /users/{user}`, `PATCH /users/{user}`, `DELETE /users/{user}`, `POST /users/{user}/status`, `PUT /users/{user}/roles` | autenticado, permissão do catálogo por operação |
| Papéis | `POST /roles`, `GET /roles`, `DELETE /roles/{role}`, `PUT /roles/{role}/permissions` | autenticado, permissão do catálogo por operação |
| Auditoria | `GET /activity-log` | autenticado, permissão de leitura da trilha |

Regras de negócio aplicadas: senha mínima de 12 caracteres, CPF opcional com unicidade parcial, e-mail único com normalização para fins de throttle, conta desativada bloqueada no perfil e nas autorizações, papel `admin` imutável, último administrador protegido contra remoção ou desativação, e dados pessoais sensíveis redigidos na trilha de auditoria.

## 6. Autenticação, sessão e segurança

A autenticação é exclusivamente por sessão em cookie. O middleware `DisallowHeaderAuthentication` bloqueia qualquer tentativa de autenticação por cabeçalho `Authorization`, de modo que tokens pessoais do Sanctum não são aceitos como credencial nesta API.

Domínios stateful e CORS são configurados por ambiente. Em desenvolvimento, `SANCTUM_STATEFUL_DOMAINS` inclui `localhost` e `127.0.0.1` com as portas do SPA e da API, e `CORS_ALLOWED_ORIGINS` autoriza a origem do SPA com `supports_credentials` habilitado. Em produção, as variáveis `CORS_ALLOWED_ORIGINS`, `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` devem conter os domínios HTTPS reais da implantação, sem curingas e sem reutilizar os valores `localhost`. Os domínios reais são fornecidos pelo responsável pelo ambiente.

O hash de senhas usa argon2id como padrão, com memória, threads e tempo ajustáveis por `ARGON_MEMORY`, `ARGON_THREADS` e `ARGON_TIME`. O cookie de sessão é `http_only` por padrão, com `same_site=lax` e tempo de vida de 120 minutos.

A limitação de taxa usa os limitadores definidos no `AppServiceProvider`:

| Limitador | Limite |
| --- | --- |
| `api` | 60 requisições por minuto por usuário autenticado ou IP |
| `login` | 5 por minuto por combinação de e-mail e IP, mais 20 por minuto por IP |
| `register` | 5 por minuto por IP |
| `verification` | 3 por minuto por usuário ou IP |
| `forgot` | 3 por minuto por IP |
| `reset` | 5 por minuto por combinação de e-mail e IP |

A chave de throttle do login e do reset usa o hash SHA-256 da combinação normalizada de e-mail e IP, evitando expor o endereço de e-mail na chave de cache.

Respostas de erro da API são sempre JSON. Exceções de autenticação retornam 401, validações retornam 422 e erros inesperados retornam 500 com mensagem genérica quando o debug está desligado. Toda resposta passa pelos middlewares `CorrelateRequest`, que propaga o identificador de correlação, e `ApplyApiSecurityHeaders`, que aplica os cabeçalhos de segurança. O Nginx de produção envia HSTS com `max-age=31536000`. Logs de acesso do Nginx estão desabilitados para não capturar query strings, e credenciais ou dados pessoais nunca devem ser registrados em log.

## 7. Filas, jobs e agendamento

A fila padrão é `database`, com despacho após commit habilitado por `QUEUE_AFTER_COMMIT=true` nos ambientes local e de teste. Isso garante que um job só seja enfileirado depois que a transação que o originou for confirmada. Para executar o trabalho de forma síncrona, defina `QUEUE_CONNECTION=sync`.

O e-mail padrão em desenvolvimento é `log`. Para capturar mensagens com interface web, ative o perfil `async`, que adiciona Redis, Mailpit e os containers de `worker` e `scheduler` exclusivos para desenvolvimento:

```sh
docker compose --profile async up -d worker scheduler
```

O worker do perfil `async` consome a fila do banco de dados e envia e-mail pelo Mailpit via SMTP em `mailpit:1025`. A interface do Mailpit fica disponível somente em localhost, na porta 8025 por padrão, sobrescrita por `MAILPIT_PORT`. Redis e SMTP não publicam portas no host.

Para um worker avulso sem o perfil, com limites de execução:

```sh
docker compose exec api php artisan queue:work database --queue=default --tries=3 --backoff=5,15,60 --max-jobs=100 --max-time=3600
```

O helper `scripts/async_dev.sh` conduz a pilha assíncrona com nome de projeto Compose dedicado, portas restritas a 127.0.0.1 e verificação sintética ponta a ponta pelo worker real, com limpeza ao final:

```sh
ENV_FILE=/caminho/para/.env-primario sh scripts/async_dev.sh up
ENV_FILE=/caminho/para/.env-primario sh scripts/async_dev.sh smoke
ENV_FILE=/caminho/para/.env-primario sh scripts/async_dev.sh cleanup
```

O agendamento diário executa três limpezas: `queue:prune-failed --hours=168` remove jobs falhados com mais de sete dias, `auth:prune-expired-tokens` remove tokens de redefinição de senha expirados e contas não verificadas com mais de 24 horas, e `activitylog:clean` aplica a retenção da trilha de auditoria.

## 8. Arquitetura de código

| Diretório | Responsabilidade |
| --- | --- |
| `app/Http/Controllers/Auth` | login, registro, logout, perfil próprio, recuperação de senha e verificação de e-mail |
| `app/Http/Controllers/Users` | CRUD de usuários, transição de estado e atribuição de papéis |
| `app/Http/Controllers/Roles` | CRUD de papéis e substituição do conjunto de permissões |
| `app/Http/Controllers/Audit` | leitura paginada da trilha de auditoria |
| `app/Http/Requests` | validação e normalização das entradas por operação |
| `app/Http/Middleware` | bloqueio de autenticação por cabeçalho, exigência de sessão stateful, correlação e cabeçalhos de segurança |
| `app/Actions` | casos de uso de mutação privilegiada com transação e auditoria atômica |
| `app/Policies` | regras por objeto para usuários e papéis |
| `app/Enums` | catálogo de permissões executável |
| `app/Models` | modelos Eloquent, incluindo usuário com CPF e desativação |
| `app/Support` | mensagens de API, auditoria, correlação, filtros, transações de privilégio e verificações de prontidão |
| `app/Providers` | limitadores de taxa e registro dos gates do catálogo |
| `app/Mail`, `app/Notifications`, `app/Jobs` | entrega assíncrona de e-mails e trabalhos em fila |
| `app/OpenApi` | atributos e respostas reutilizáveis do Scramble |
| `bootstrap/app.php` | prefixo `api/v1`, middlewares globais e renderização JSON de exceções |
| `routes/api.php` | definição das rotas versionadas com throttles e gates por operação |
| `routes/console.php` | comandos e agendamento das limpezas diárias |
| `config/` | sessão, Sanctum stateful, CORS, fila, hashing argon2id, permissões e auditoria |
| `database/migrations` | esquema, incluindo unicidade parcial de CPF |
| `contracts/openapi.json` | snapshot versionado do contrato OpenAPI |
| `docker/` | configurações separadas para desenvolvimento e produção |
| `scripts/` | ambiente local, pilha assíncrona e verificação de contrato |

Mutações privilegiadas passam por `PrivilegeTransaction`: a operação e o evento de auditoria são confirmados no mesmo commit transacional, e qualquer falha reverte a mutação. A leitura da trilha usa filtros dedicados em `App\Support\Filters` e nunca expõe dados sensíveis sem redação.

## 9. Documentação da API com OpenAPI versionado

O Scramble gera a documentação a partir das rotas sob `api/v1` e `api/v1/*`, com título, versão vinda de `API_VERSION` e descrição da autenticação por sessão para o SPA próprio. A interface interativa usa o renderizador Elements, e a variante Scalar está configurada como alternativa. As ferramentas de desenvolvimento do Scramble acompanham o modo debug, com liberação explícita fora dele por `SCRAMBLE_DEV_TOOLS`.

O contrato canônico é o snapshot `contracts/openapi.json`, no formato OpenAPI 3.1.0. Ele é produzido pelo workflow manual **OpenAPI Contract**, executado exclusivamente na branch `main` após a integração de mudanças de rotas ou contratos. O produtor exporta o contrato duas vezes para verificar determinismo, publica o artefato `openapi-<commit>` e o snapshot só entra no repositório por commit próprio após revisão. O gate `contract` do CI confirma que o snapshot commitado corresponde ao contrato atual. Exportações locais não substituem o produtor oficial.

O repositório frontend consome esse snapshot como entrada do gerador de tipos, de modo que qualquer mudança de contrato precisa passar pela atualização do snapshot antes de gerar o cliente.

## 10. Docker e perfil release

A imagem de produção usa construção em múltiplos estágios. O estágio `composer` fornece o binário do Composer 2. O estágio `vendor` parte de `php:8.5-fpm-bookworm`, instala as extensões `mbstring`, `pdo_pgsql` e `zip` e executa `composer install` de produção com autoloader otimizado. O estágio `runtime` adiciona Nginx, Supervisor, opcache e a extensão Redis, cria o usuário de sistema `app` com UID e GID 10001, copia o código com propriedade desse usuário, instala as configurações de produção e publica o pacote com `artisan package:discover`.

O `php.ini` é separado por ambiente. O arquivo `docker/local/php.ini` valida timestamps de opcache para recarregar código a cada requisição em desenvolvimento. O arquivo `docker/production/php.ini` desliga a validação de timestamps, reserva 256 MB para opcache, amplia o cache de caminhos reais e mantém o JIT desligado. Ambos mantêm `expose_php` desligado e `zend.assertions` em modo de produção.

O container de release executa Nginx mais PHP-FPM sob Supervisor como usuário `app`, expõe a porta 8080 e declara healthcheck contra `GET /api/v1/health`. Sessão e cache usam Redis no release, e os logs saem em JSON pelo stderr com `LOG_CHANNEL=stderr` e `LOG_STDERR_FORMATTER` apontando para o `JsonFormatter` do Monolog, prontos para coleta centralizada. O desenvolvimento local mantém logs legíveis em arquivo único.

O TLS termina no edge, nunca no container da API: o perfil release serve HTTP puro na porta 8080 atrás do proxy, e o HSTS aplicado pelos cabeçalhos de segurança garante a elevação para HTTPS nos navegadores. A porta do release nunca deve ser exposta diretamente à internet sem um proxy com terminação TLS à frente.

Releases são imagens OCI imutáveis publicadas no GHCR com tag `api-<data-UTC>-<sha-de-12-chars>`. Tags de release nunca são movidas nem sobrescritas. O deploy troca o projeto Compose remoto para a tag, executa as migrations, reinicia API, worker e scheduler e roda a verificação reutilizável de login, perfil próprio e logout. O rollback aponta `API_IMAGE` para a tag imutável anterior e repete a subida dos serviços, seguida da mesma verificação.

## 11. Testes e qualidade

A suíte principal usa Pest contra PostgreSQL isolado. O serviço `test` do Compose aguarda o banco de teste, o Redis de teste e o Mailpit de teste saudáveis e executa `composer test` dentro do container, sem publicar portas no host:

```sh
docker compose --profile test run --rm test
```

O banco de teste usa armazenamento efêmero, credenciais próprias e rede interna dedicada, com a conexão forçada por variáveis de ambiente para impedir o uso acidental dos padrões de desenvolvimento.

Os gates de qualidade executados no CI e localmente:

```sh
docker compose run --rm --no-deps api composer validate --strict
docker compose run --rm --no-deps api composer check-platform-reqs
docker compose run --rm --no-deps api composer audit
docker compose run --rm --no-deps api composer lint
docker compose run --rm --no-deps api composer analyse
docker compose --profile test run --rm test
```

O `composer test` executa o Pest. O `composer lint` executa o Pint em modo somente verificação com o preset `laravel`. O `composer analyse` executa o PHPStan com Larastan no nível 10 sobre aplicação, bootstrap, rotas e testes PHP, sem linha de base de supressão. O job de cobertura do CI usa pcov e exige mínimo de 85% com `vendor/bin/pest --coverage --min=85`. O workflow de contrato valida compatibilidade do `contracts/openapi.json` contra a linha de base, e o workflow de smoke reutilizável exercita login, perfil próprio e logout contra o ambiente publicado. As definições progressivas dos gates vivem em `QUALITY_GATES.md`.

## 12. Integração fullstack

O repositório `starter-kit-frontend`, um SPA com Vue 3.5 e Quasar 2.34, consome esta API como origem independente. O frontend nunca envia credenciais por cabeçalho: ele trabalha com a mesma sessão em cookie emitida pela API, com CSRF validado a cada mutação.

O fluxo de login no navegador segue esta ordem. O cliente HTTP cria a instância axios com `withCredentials` habilitado, configurada a partir de `VITE_API_URL`, que aponta para a raiz da API sem caminho nem barra final. Antes da primeira chamada autenticada, o SPA requisita `GET /sanctum/csrf-cookie` para obter o cookie XSRF. As mutações seguintes enviam o cookie de sessão e o token CSRF juntos, e a API responde dentro da sessão stateful validada pelo Sanctum.

Para que isso funcione entre origens, os dois lados precisam concordar. O Vite serve o SPA em `http://localhost:5173` enquanto a API escuta em outra porta. O lado da API autoriza essa origem exatamente em `CORS_ALLOWED_ORIGINS`, com `supports_credentials` habilitado, e lista os hosts das duas aplicações em `SANCTUM_STATEFUL_DOMAINS`, sem esquema. Quando API e SPA passam a compartilhar um domínio pai em produção, `SESSION_DOMAIN` recebe esse domínio compartilhado para que o mesmo cookie de sessão valha nas duas pontas, com `SESSION_SECURE_COOKIE` e `SESSION_SAME_SITE` ajustados para o contexto HTTPS.

Os tipos TypeScript do frontend são gerados pelo Orval 8.39 a partir do contrato OpenAPI pinado. O arquivo `orval.config.ts` aponta a entrada para o snapshot do contrato e a saída para o cliente `vue-query` sobre axios, com o mutador `transportApi` centralizando base URL, credenciais e CSRF. Os arquivos gerados carregam o cabeçalho de procedência do contrato e nunca são editados manualmente; a regeneração usa `npm run client:generate` e a verificação de sincronia usa `npm run client:check`.

As guards de rota do SPA tratam os dois códigos de falha de acesso da API. Respostas 401 levam de volta ao login, e respostas 403 levam à página dedicada `/403`, com testes cobrindo ambos os caminhos. O contrato versionado garante que os formatos de erro, os códigos de estado e as permissões exigidas por rota estejam alinhados entre os dois repositórios antes de qualquer entrega.

## 13. Boas práticas

Respostas de erro seguem o catálogo central em `App\Support\ApiMessage`, sem vazar detalhes internos quando o debug está desligado. O `APP_DEBUG` permanece `false` em todos os ambientes Compose, incluindo desenvolvimento. Exceções de validação retornam 422 com o detalhe por campo; falhas inesperadas retornam 500 com mensagem genérica.

Nunca versione o `.env`, segredos, credenciais geradas ou dados pessoais. Os logs de acesso do Nginx ficam desligados por padrão para não registrar query strings, e entradas de negócio sensíveis passam por correlação e redação antes de qualquer registro. A documentação de segurança em `docs/security-configuration.md` lista as variáveis que o responsável pelo ambiente deve fornecer antes de publicar.

Migrations de desenvolvimento são incrementais e reversíveis; `migrate:fresh` é proibido fora de contextos efêmeros. Volumes de banco nunca são removidos como rotina, e comandos destrutivos exigem nome de projeto explícito quando há mais de um checkout na máquina.

Dependências passam por `composer audit` no CI e por Dependabot no GitHub. Imagens Docker usam digests pinados, e o lockfile do Composer é a fonte canônica das versões PHP instaladas.

## 14. Convenções de commit

O histórico do repositório segue commits convencionais em minúsculas, com escopo entre parênteses quando a mudança pertence a uma área. Os tipos usados são `feat`, `fix`, `docs`, `chore` e `ci`, como mostram as entradas recentes: `feat(bloco3)`, `fix(ci)`, `docs`, `chore(contract)` e `ci(coverage)`.

Cada entrega agrupa a mudança de comportamento com seus testes, a atualização do snapshot OpenAPI quando rotas ou contratos mudam, e a documentação afetada. O assunto descreve o efeito, sem ponto final, e o corpo explica o motivo quando a decisão não é óbvia a partir do diff.

Mudanças de contrato exigem a atualização do `contracts/openapi.json` em commit próprio após revisão do artefato do produtor, nunca por export local direto. Tags de versão `v*` disparam o workflow de release, mas o artefato publicado sempre recebe a tag imutável gerada `api-<data>-<sha>`, que nunca é reutilizada.

## 15. Padrões de código

Todo arquivo PHP abre com `declare(strict_types=1)` e usa tipagem estrita em parâmetros e retornos. Controllers delegam regra de negócio para actions, form requests validam a entrada na borda e policies decidem o acesso por objeto. Mensagens de erro reutilizáveis vivem em `App\Support\ApiMessage` para manter o vocabulário da API consistente entre controllers, middlewares e tratamento de exceções.

A formatação segue o preset `laravel` do Pint, verificado em modo somente leitura pelo `composer lint`. A análise estática com Larastan no nível 10 não admite linha de base: o código deve passar limpo sobre aplicação, bootstrap, rotas e testes. Testes usam Pest com suítes por funcionalidade sob `tests/Feature`, cobrindo partida, disponibilidade JSON, limites de rota, isolamento do banco de teste e casos negativos de acesso.

Nomes de permissões, habilidades de gate e chaves de throttle usam o vocabulário do catálogo central, sem strings espalhadas. Chaves de throttle que envolvem identidade do usuário passam por hash antes de alcançar o cache. Novos endpoints nascem com rota versionada sob `api/v1`, throttle dedicado, gate ou policy correspondente, cobertura de teste, entrada no snapshot OpenAPI e documentação atualizada.
