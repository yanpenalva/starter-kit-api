# 🧾 Receita de proveniência do artifact OpenAPI

Use esta receita quando o contrato ou seus tipos forem consumidos fora do
repositório da API. O workflow `OpenAPI Contract` publica um JSON em um artifact;
ele não cria commit nem gera um receipt de proveniência automaticamente.

## Identifique e baixe o artifact

1. Use uma execução concluída com sucesso do workflow oficial em `main`. Confira o
   nome e caminho do workflow, o evento permitido, o SHA completo, o ID da execução
   e o ID do artifact. O nome esperado é `openapi-<SHA completo>`; `latest` não é
   uma identidade válida.
2. Confirme que o artifact ainda não expirou. A retenção configurada é de 90 dias.
   Se estiver expirado, execute novamente o produtor em `main` e use os metadados
   dessa nova execução; não atribua um artifact novo a um SHA histórico.
3. Baixe o ZIP bruto pelo transporte local autorizado `gh`. A rotina
   `scripts/contract_fetch.py` verifica os metadados do run e do artifact antes de
   baixar, calcula o SHA-256 do ZIP e extrai o JSON com as verificações de segurança
   de `scripts/contract_extract.py`.
4. Registre separadamente o SHA-256 do ZIP (`archive_sha256`) e o SHA-256 do JSON
   extraído (`artifact_file_sha256`). Eles identificam bytes diferentes e não
   podem ser intercambiados.

## Monte e verifique o receipt

O receipt JSON contém estes campos, obtidos dos metadados confiáveis da execução e
dos hashes calculados durante o download: `workflow_name`, `workflow_path`,
`workflow_id`, `run_id`, `run_url`, `artifact_id`, `artifact_name`, `commit_sha`,
`head_branch`, `event`, `created_at`, `archive_sha256` e `artifact_file_sha256`.
`scripts/contract_receipt.py` valida a estrutura do receipt; ele é um módulo Python,
sem comando CLI próprio.

Veja as opções do verificador executável:

```sh
python3 scripts/contract_cli.py --help
```

Para cada verificação, forneça `--artifact-file`, `--receipt-file` e o SHA completo
esperado em `--expected-sha`. Use `--mode download-fresh` para uma recuperação
recente: o verificador aplica a idade máxima de 90 dias por padrão e rejeita
timestamps mais de cinco minutos no futuro. Use `--mode verify-committed` para
spec e receipt já versionados pelo consumidor; esse modo valida identidade e hashes
sem fazer o CI falhar quando os bytes commitados ultrapassarem a retenção do
artifact. Quando aplicável, fixe também `--expected-workflow-id` e reduza
`--allowed-events` ao evento autorizado para aquele fluxo.

O verificador falha fechado se SHA, branch, workflow, evento, artifact, IDs,
estrutura do receipt ou hash do JSON divergirem. Não aceite arquivo ou receipt por
nome `latest`, não use um receipt de outra execução e não prossiga se a verificação
falhar.

## Registre o limite da evidência

O receipt registra proveniência dos metadados do GitHub Actions e integridade dos
bytes transferidos. A verificação não é assinatura criptográfica nem atestação de
quem produziu o artifact. Preserve o SHA da API, run/workflow/artifact IDs, hashes,
versão do gerador e a revisão consumidora junto ao contrato versionado.

Depois de revisar o JSON, siga o [guia de release do contrato](contract-release.md)
para compará-lo com o snapshot integrado na mesma revisão de main. O snapshot
candidato de uma mudança de API é revisado e versionado no PR; não copie o artifact
extraído nem use uma exportação local para simular a publicação oficial.
