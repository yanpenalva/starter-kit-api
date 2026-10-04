# Reproduction — BUG-38

## Observed behavior

- O primeiro queue worker em `tests/Feature/InfraQueueFailureTest.php` foi invocado com argumentos `--stop-when-empty --max-jobs=50 --tries=2 --backoff=1`.
- Caso o processo do worker demore mais de 1 segundo (tempo de boot do framework, garbage collection, latência do runner), o job colocado no delayed zset (`queues:testqueue:delayed`) expira seu backoff de 1s e é repuxado pelo loop do worker antes do encerramento.
- Como o worker possui `--max-jobs=50`, ele consome a segunda tentativa, esgota as tentativas (`tries=2`) e grava o registro na tabela `failed_jobs`.
- A asserção subsequente `$this->assertSame(0, (int) DB::table('failed_jobs')->count());` na linha 39 falha com: `Failed asserting that 1 is identical to 0.`

## Expected behavior

- A primeira execução do worker deve realizar estritamente uma única tentativa (`--once`).
- Após a falha inicial, o job deve ser re-enfileirado no Redis `queues:testqueue:delayed` com backoff de 1s.
- `failed_jobs` deve conter 0 registros antes do término da janela de espera do backoff.
- Somente após o `usleep` e a execução do segundo worker o job deve ser processado na segunda tentativa e persistido em `failed_jobs`.

## Steps and evidence

1. Executar a suíte de testes com backend Redis real (`composer test` ou `./vendor/bin/pest tests/Feature/InfraQueueFailureTest.php`).
2. Evidência no CI: GitHub Actions run 37232150173, job 111523972664 (`application`), falha na linha 39 de `tests/Feature/InfraQueueFailureTest.php`.

## Scope

- Reproducibility: Intermitente a reprodutível dependendo de latência de CPU/IO no runner do CI.
- Affected versions/environments: CI GitHub Actions e ambientes com execução delimitada do queue worker.
- Suspected boundary: Invocação do primeiro worker em `tests/Feature/InfraQueueFailureTest.php:35`.
