# 📬 Starter Kit API — Insomnia

Importe `starter-kit-api-insomnia.json` em **Import → File** no Insomnia com suporte a v5. São **37 requests em 5 pastas**: Auth (20), Users (7), Roles (6), Audit (1) e Health (3). Os exemplos de resposta (status e corpo) ficam na descrição de cada request. Exemplos corrigidos usam capturas HTTP sanitizadas; os demais exemplos sintéticos e erros ilustrativos estão identificados. A validação live e seus limites estão em [VALIDATION.md](VALIDATION.md), com [capturas reais](live-responses.json).

O arquivo usa a estrutura nativa `type: collection.insomnia.rest/5.0` e inclui `_type: export`, `__export_format: 5` e `resources` conforme solicitado. A árvore `collection` é a representação usada pelo importador v5; `resources` espelha os mesmos IDs/requests, sem criar outra collection. Ao editar manualmente, mantenha ambas alinhadas. Referências: [importação oficial](https://developer.konghq.com/insomnia/import-export/) e [schema oficial](https://raw.githubusercontent.com/Kong/insomnia/develop/schemas/insomnia.schema.5.1.json).

## Environment e autenticação

Selecione **Local**, que herda **Base Environment**. Defaults: `base_url=http://localhost:8000`, `api_prefix=/api/v1`, `origin=http://localhost:5173`. URLs não devem terminar com `/`. Configure `origin` para uma origem confiável em `SANCTUM_STATEFUL_DOMAINS`; a API precisa reconhecer a chamada como stateful. Para staging, crie um subenvironment com a URL e origem reais: não há domínio de staging definido no repo. Use um cookie jar separado por ambiente.

1. Configure `email` e `password` de uma conta real. Os valores exportados são fictícios; não há credenciais provisionadas. Registro usa e-mail único e senha confirmada de pelo menos 12 caracteres. Usuário não verificado tem acesso limitado; administração depende de conta elegível, permissões e policies.
2. Envie **GET /sanctum/csrf-cookie**. Mantenha envio/armazenamento de cookies habilitados (já configurados). No cookie jar, copie o valor **URL-decoded** de `XSRF-TOKEN` para `xsrf_token`; mutações enviam `X-XSRF-TOKEN`.
3. Envie **POST /api/v1/login**: sucesso comum retorna **204 e estabelece sessão via cookie**. Não configure Bearer, Authorization ou personal access tokens. Se retornar **200 `{ "requires_mfa": true }`**, atualize `xsrf_token` e envie o challenge com TOTP real ou `recovery_code` de uso único.
4. Leia **GET /api/v1/auth/my-profile**. Os demais requests usam o cookie de sessão automaticamente. Atualize `xsrf_token` sempre que o cookie mudar, especialmente após login, challenge ou logout. Em 419, refaça CSRF e confira origem/cookies. **POST logout** encerra a sessão.

IDs em path são variáveis de environment: `user_id`, `role_id`, `session_id` e os campos `verification_*`. Substitua pelos valores obtidos na API. Para verificação de e-mail, copie os componentes do link assinado sem alterar URL, origem, path ou query; hash/signature/expires fictícios não funcionam. `reset_token` vem do e-mail de recuperação e `mfa_code` deve ser um TOTP atual. Sessões exigem `SESSION_DRIVER=database`.

Bodies seguem FormRequests/validação dos controllers. Filtros opcionais têm exemplos desabilitados; `page=1` e `per_page=15` ficam ativos. PATCH de perfil altera apenas nome por padrão; sua descrição explica os campos opcionais de senha/e-mail. Requests de alteração/exclusão/status/roles modificam dados: execute individualmente em uma base de teste, ajustando IDs. CPF em Users aparece apenas quando autorizado; roles/permissões devem existir e respeitar a autorização do ator.

## Cobertura e validação

Fonte: `routes/api.php`, `routes/api/*.php`, controllers, FormRequests, Resources e leitura de `contracts/openapi.json` (não regenerado). `route:list --json` executou com PHP 8.5 e SQLite em memória, sem iniciar serviços externos.

| Escopo | Rotas | Requests |
| --- | ---: | ---: |
| `/api/v1` (inclui disponibilidade e health) | 35 | 35 |
| `/sanctum/csrf-cookie` | 1 | 1 |
| `/up` | 1 | 1 |
| `/docs/api`, `/docs/api.json` | 2 | 0 |
| Total registrado | 39 | 37 |

Cobertura da API: **35/35**; total registrado: **37/39**. GET e HEAD automáticos contam como uma rota/request GET. As duas rotas omitidas servem documentação, não operações da API. Não existe fallback registrado. O fixture condicional `/testing/csrf-echo` não estava habilitado e não é endpoint de produto. `/up` retorna JSON com o header `Accept: application/json` da collection (HTML sem negociação JSON); health pode retornar 503 quando database/redis/queue não estão prontos.

Há divergências no snapshot: `UserResource.roles` é descrito como objetos, mas o Resource implementado retorna strings; os exemplos seguem o código. `/health` também pode responder 503, embora o snapshot liste apenas 200. Mensagens traduzidas e valores dinâmicos variam; exemplos não garantem sucesso sem dados, e-mail, permissões e infraestrutura configurados.

Validações locais: schema oficial Insomnia v5.1 validado com AJV (draft 2020-12); JSON com `python3 -m json.tool`, comparação de método/path com `route:list`, integridade de IDs/parents, alinhamento das duas representações, corpos JSON e ausência de Authorization; `python3 scripts/validate_context.py --strict`. A validação HTTP real isolada passou em **35/35 operações `/api/v1`**, mais CSRF e `/up`; reprodução e limites em [VALIDATION.md](VALIDATION.md). A importação pela interface gráfica não foi executada. Não houve alteração na aplicação nem commit/push.

Registro de escopo: product/feature documental, owner solicitante, risco baixo, impacto local, perfil senior-software-engineer. Plano autorizado: mapear → gerar → validar cobertura/estrutura → entregar arquivos locais; sem entrega Git ou produção.
