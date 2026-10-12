# Onboarding de desenvolvimento

Este guia leva de um checkout limpo à suíte Pest verde. Os comandos pressupõem a raiz do repositório e Docker Compose v2.

## Pré-requisitos

- PHP 8.5 com extensões PDO e `pdo_pgsql` para executar a aplicação no host; Composer 2.
- Docker com o plugin Docker Compose v2, para os serviços e a suíte PostgreSQL isolada.
- Node.js não é necessário para desenvolver ou testar a API.

## Preparar o checkout

```sh
cp .env.example .env
composer install
php artisan key:generate
sh scripts/install-hooks.sh
```

Defina em `.env` valores locais descartáveis para `DB_PASSWORD` e `TEST_DB_PASSWORD` (o Compose exige ambos). Não use credenciais reais e não versione o arquivo `.env`. Para desenvolvimento local, o `.env.example` configura PostgreSQL no serviço `postgres`; a suíte de CI usa `postgres-test`, Redis e Mailpit separados.

Para iniciar a base de desenvolvimento e aplicar migrations e dados de exemplo, use a imagem/serviço `api` do Compose (o PostgreSQL de desenvolvimento não publica porta no host):

```sh
docker compose build api
docker compose run --rm api php artisan migrate --seed
```

O banco de desenvolvimento é persistente no volume `postgres-dev`. Para um teste efêmero e isolado que corresponde à execução de CI, rode:

```sh
docker compose --profile test run --rm test
```

O serviço `test` aguarda `postgres-test`, `redis-test` e `mailpit-test` saudáveis e executa `composer test` dentro do container. PostgreSQL usa armazenamento temporário e os três serviços de teste não precisam de portas expostas no host. O comando acima é o caminho recomendado para obter `composer test` verde do zero sem instalar extensões de Redis no host.

Com os serviços de desenvolvimento ativos, a suíte pode também ser executada diretamente no host:

```sh
composer test
```

Execute as verificações estáticas:

```sh
composer lint
composer analyse
```

`lint` executa Pint em modo somente verificação; `analyse` executa Larastan/PHPStan nível 10.

## Fila e scheduler em desenvolvimento

Para os processos assíncronos locais, inicie os serviços do perfil `async` (incluem worker, scheduler, Redis e Mailpit):

```sh
docker compose --profile async up -d postgres redis mailpit worker scheduler
```

Ou execute os workers no processo da aplicação, com configuração local apropriada:

```sh
php artisan queue:work
php artisan schedule:work
```

## Workflows e gates

O workflow **Quality** executa em pull requests e em pushes para `main`. São gates independentes:

- **context**: validação estrita de contexto (com e sem exemplos), testes Python de validação, instalação verificada de `oasdiff` e diagnóstico CSD;
- **application**: Composer validate/install/requisitos/audit, Pint, Larastan e Pest contra PostgreSQL 18, Redis e Mailpit reais do job;
- **contract**: migra banco, exporta o OpenAPI duas vezes e exige bytes idênticos, snapshot versionado atualizado, estrutura contratual válida e ausência de breaking changes contra o baseline imutável.

Os comandos locais de validação de contexto estão em [quality configuration](../.context/project/quality.md). Os workflows são a fonte executável dos requisitos completos de cada job.

## OpenAPI

Siga o [guia de release do contrato](contract-release.md) para executar o produtor em main e atualizar o snapshot revisado. A [receita de proveniência](openapi-contract-recipe.md) detalha a verificação do artifact; a exportação local serve apenas para inspeção. Para implementar uma mudança fim a fim, use também a [receita de fatia vertical](vertical-slice-recipe.md).

## Deploy e smoke

Deploy, rollback e smoke manuais estão documentados em [release.md](release.md); consulte esse guia em vez de duplicar seus passos operacionais aqui.
