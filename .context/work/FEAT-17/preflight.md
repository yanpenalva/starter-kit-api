# Preflight — FEAT-17

- [x] Regra nova, escopo e aceite explícitos pelo solicitante.
- [x] Perfil padrão descoberto no código/seeder e aprovado em FEAT-6: `user`, sem permissões.
- [x] PUT verificado: `AssignUserRolesRequest` já rejeita lista vazia; nenhuma mudança necessária.
- [x] Endpoints, requests, testes e documentação proprietária identificados.
- [x] Nenhuma expansão do catálogo, migration, mudança de autenticação ou artefato OpenAPI neste branch.
- [x] Commit e push para a branch/remote solicitados explicitamente; sem force push, merge ou deploy.
- [x] Vínculo descoberto: issue #17 cobre criação/gestão administrativa; está fechada e não será reaberta nem alterada.

## Verdict

Pronto para execução local. `vendor/` é um symlink não rastreado preexistente e será preservado fora do commit.
