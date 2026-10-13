# Validação HTTP real da collection

Executada em 2026-10-13, PHP 8.5 / Laravel instalado: **35/35 operações `/api/v1` validadas live**, mais `/sanctum/csrf-cookie` e `/up`. Todas as operações funcionais chegaram ao sucesso esperado; health retornou o 503 esperado para Redis ausente. Foram capturadas 62 respostas HTTP. Capturas completas de status, Content-Type e corpo, incluindo negativos: [live-responses.json](live-responses.json). Rotas registradas na mesma configuração: [live-routes.json](live-routes.json). Não houve commit/push nem alteração na aplicação ou no snapshot OpenAPI.

Servidor PHP nativo em `127.0.0.1`, porta efêmera dedicada; SQLite em diretório temporário privado dentro de `storage/app`, apagado ao terminar. Chave e senhas geradas em memória, sessões/cache em SQLite, fila sync, mail log como proteção adicional e `Notification::fake()` no bootstrap do servidor. O kernel, controllers, middleware, CSRF, policies e cookies são reais; somente a entrega de notificações é interceptada. Fixture CLI cria um administrador verificado e catálogo RBAC; as demais mutações usam HTTP. Sem Docker ou serviços externos. Redis é configurado para loopback em porta livre sem serviço; health observa sua indisponibilidade. URLs de conexão herdadas e cache de configuração são neutralizados; o cliente HTTP ignora proxies.

O fluxo foi CSRF → registro → login → reenvio/link assinado → perfil/senha/sessões/reset → admin/MFA → users/roles → exclusões → auditoria/logout. Exclusões usam apenas entidades criadas nessa execução. O seed original não cria cache: o harness provisiona `cache`/`cache_locks` apenas no SQLite descartável; migrações usam cache array e o servidor usa cache database, preservando proteção de reuso entre requests.

“Divergente → corrigido” compara o exemplo original com a captura, ignorando IDs, datas, origem, cardinalidade e dados de fixtures. CPF é condicional à policy; sua ausência não era corpo inválido, mas o exemplo administrativo estava incompleto. “OK” significa o cenário observado, não todos os possíveis ramos. Exemplos de erro sintéticos não exercitados continuam identificados como ilustrativos. Headers, bodies e parâmetros dos requests foram conferidos; não exigiram correção. As duas representações (`collection` e `resources`) foram atualizadas em conjunto.

| Método | Rota | Status real | Comparação | Nota |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/auth/mfa/challenge` | 204, 422 | OK | TOTP e recovery: 204; replay de ambos: 422. |
| POST | `/api/v1/auth/mfa/confirm` | 200 | OK | TOTP calculado independentemente em Python; confirmed=true. |
| POST | `/api/v1/auth/mfa/disable` | 204 | OK | Status/corpo compatíveis com o exemplo. |
| POST | `/api/v1/auth/mfa/enroll` | 200 | OK | Status/corpo compatíveis com o exemplo. |
| POST | `/api/v1/auth/mfa/recovery-codes` | 200 | OK | Status/corpo compatíveis com o exemplo. |
| GET | `/api/v1/auth/mfa/status` | 200 | OK | Status/corpo compatíveis com o exemplo. |
| GET | `/api/v1/auth/my-profile` | 200, 401 | Divergente → corrigido | role_display_names ausente no exemplo original; vazio como objeto quando não verificado. |
| PATCH | `/api/v1/auth/my-profile` | 200 | Divergente → corrigido | role_display_names ausente no exemplo original; vazio como objeto quando não verificado. |
| PUT | `/api/v1/auth/password` | 200 | OK | Status/corpo compatíveis com o exemplo. |
| DELETE | `/api/v1/auth/sessions` | 204 | OK | Sem corpo; entidade/sessão de teste existente. |
| GET | `/api/v1/auth/sessions` | 200 | OK | Duas sessões reais; IDs opacos sanitizados. |
| DELETE | `/api/v1/auth/sessions/{id}` | 204 | OK | Sem corpo; entidade/sessão de teste existente. |
| POST | `/api/v1/email/verification-notification` | 202 | OK | Status/corpo compatíveis com o exemplo. |
| GET | `/api/v1/email/verify/{id}/{hash}` | 200 | OK | Link da notification fake; assinatura validada pelo middleware real. |
| POST | `/api/v1/forgot-password` | 202 | OK | Notificação capturada via fake; sem envio SMTP. |
| POST | `/api/v1/login` | 200, 204, 419 | OK | 204 normal; 200 requires_mfa; 419 sem X-XSRF-TOKEN. |
| POST | `/api/v1/logout` | 204 | OK | Status/corpo compatíveis com o exemplo. |
| POST | `/api/v1/register` | 201 | OK | Status/corpo compatíveis com o exemplo. |
| POST | `/api/v1/reset-password` | 200 | OK | Token da notification fake; sessão anterior passou a 401. |
| GET | `/api/v1/users` | 200 | Divergente → corrigido | role_display_names e CPF autorizado ausentes no exemplo original. |
| POST | `/api/v1/users` | 201 | Divergente → corrigido | role_display_names e CPF autorizado ausentes no exemplo original. |
| DELETE | `/api/v1/users/{user}` | 204 | OK | Sem corpo; entidade/sessão de teste existente. |
| GET | `/api/v1/users/{user}` | 200 | Divergente → corrigido | role_display_names e CPF autorizado ausentes no exemplo original; verificação depende dos dados. |
| PATCH | `/api/v1/users/{user}` | 200 | Divergente → corrigido | role_display_names e CPF autorizado ausentes no exemplo original; verificação depende dos dados. |
| PUT | `/api/v1/users/{user}/roles` | 200 | Divergente → corrigido | role_display_names e CPF autorizado ausentes no exemplo original. Também anexada role criada via HTTP. |
| POST | `/api/v1/users/{user}/status` | 200 | Divergente → corrigido | role_display_names e CPF autorizado ausentes no exemplo original. |
| GET | `/api/v1/roles` | 200 | Divergente → corrigido | display_name ausente no exemplo original. |
| POST | `/api/v1/roles` | 201 | Divergente → corrigido | display_name ausente no exemplo original. |
| DELETE | `/api/v1/roles/{role}` | 204 | OK | Sem corpo; entidade/sessão de teste existente. |
| GET | `/api/v1/roles/{role}` | 200 | Divergente → corrigido | display_name ausente no exemplo original. |
| PATCH | `/api/v1/roles/{role}` | 200 | Divergente → corrigido | display_name ausente no exemplo original. |
| PUT | `/api/v1/roles/{role}/permissions` | 200 | Divergente → corrigido | display_name ausente no exemplo original. |
| GET | `/api/v1/activity-log` | 200 | OK | Status/corpo compatíveis com o exemplo. |
| GET | `/api/v1` | 200 | OK | Status/corpo compatíveis com o exemplo. |
| GET | `/api/v1/health` | 503 | OK | Cenário degradado real: database=true, redis=false, queue=true; exemplo saudável 200 continua válido. |

Fora do denominador: CSRF **204** com cookie jar; `/up` **200 application/json**, corpo `{"status":"up"}`. A descrição HTML de `/up` divergia do próprio header Accept; foi corrigida. O exemplo saudável de health 200 foi preservado e o exemplo degradado passou a mostrar a captura real `database=true, redis=false, queue=true`.

## Divergências do OpenAPI

`contracts/openapi.json` foi somente lido, sem regeneração ou edição:

- `components.schemas.UserResource.properties.roles.items` descreve objetos com `relations`; live retorna strings (`"user"`, `"admin"` e nome da role criada), em listagem/show/mutações.
- `GET /api/v1/health` lista somente 200; live observou 503 com o mesmo formato de corpo e Redis ausente.
- `role_display_names` em MyProfileResource/UserResource e `display_name` em RoleResource já existem no snapshot como propriedades opcionais; eram omissões da collection, não propriedades ausentes no OpenAPI.

## Não validado via live

- Entrega SMTP, processamento por worker, frontend que abre links e entrega real de e-mail: notifications fake, fila sync; os links/tokens produzidos foram usados em chamadas HTTP reais.
- Aplicativo autenticador, QR scan, dispositivo/relógio externo: TOTP SHA1/6 dígitos/30s foi calculado independentemente em Python, com self-check RFC 6238, e aceito pelo servidor real. Confirmação usa janela atual; challenge usa janela seguinte dentro da tolerância ±1 para evitar reuso. Fluxos de TOTP, recuperação e rejeição de reuso foram validados live; integração com aplicativo não foi.
- Health saudável com Redis, PostgreSQL 18, Redis real, HTTPS/cookies Secure, staging/produção: restrição de isolamento; health 503 observado não comprova readiness completa. Nenhum desses serviços foi acessado.
- Todos os ramos de erro, limites de rate limiting, concorrência e importação pela interface gráfica do Insomnia: fora desta execução. Foram exercitados especificamente 419 sem CSRF, 401 após revogação/reset e 422 por replay MFA/recovery. `/up` 500 foi conferido no framework, não provocado live.

## Como reproduzir

Na raiz do repo, com dependências Composer já instaladas:

```sh
. /opt/data/php/env.sh
python3 docs/insomnia/validate_live.py
```

O comando inicia/encerra o servidor, recria o SQLite isolado a cada execução e sobrescreve apenas as duas capturas sanitizadas. Esperado: `PASS: 35/35 API operations + CSRF + /up; negative security assertions passed`. Não use `python3 -O`, que desativa asserts. O executável PHP é o 8.5 de `/opt/data/php/root/usr/bin/php8.5`. Nenhum `.env` é alterado. Capturas mantêm IDs numéricos/datas/UUIDs e e-mails fictícios `example.test`; segredos, CPF, URI de provisionamento, códigos de recuperação, hash de verificação e IDs de sessão são mascarados. Cookies, passwords e parâmetros de assinatura não são exportados. Arquivos privados da fake são removidos junto com o banco. URLs de paginação nas capturas são reais; nos exemplos da collection, a origem foi normalizada para `http://localhost:8000`.

Para revalidar estrutura/schema/cobertura, baixe o schema oficial e instale AJV apenas em diretório ignorado de trabalho (não altera package/lock da aplicação):

```sh
curl -fsSL https://raw.githubusercontent.com/Kong/insomnia/develop/schemas/insomnia.schema.5.1.json -o storage/app/insomnia.schema.5.1.json
npm install --prefix storage/app/insomnia-schema-tools --no-save --package-lock=false ajv@8.17.1
python3 -m json.tool docs/insomnia/starter-kit-api-insomnia.json > /dev/null
node docs/insomnia/validate_collection.cjs storage/app/insomnia.schema.5.1.json ./storage/app/insomnia-schema-tools/node_modules/ajv/dist/2020.js
python3 scripts/validate_context.py --strict
```

O schema v5.1 usa draft 2020-12; o validador aplica AJV 2020 com `strict:false` e verifica cobertura método/rota, corpos JSON, alinhamento dos mirrors e ausência de Authorization. Composer test/lint/analyse não executados: nenhuma alteração na aplicação PHP. O harness HTTP é a verificação funcional deste trabalho, com asserts de status, cobertura e cenários de segurança. Isso não substitui os gates PostgreSQL do CI.

Validação final: JSON, schema oficial v5.1 via AJV 2020, mirrors/cobertura e contexto estrito passaram. SHA-256 do schema utilizado: `8338cfa910d2f59af426c8b6576335519abc7824496a8902fb110fdca800986b`. Revisão independente aprovada sem achados materiais.
