# Reproduction — BUG-41

## Observed behavior

- Os dois resources serializam o resultado de `array_combine([], [])` como array JSON vazio (`[]`).
- PHPUnit confirmou que o mapa vazio do endpoint de perfil não é objeto.

## Expected behavior

- `role_display_names` deve ser objeto JSON também quando vazio, conforme `contracts/openapi.json`.

## Steps and evidence

1. Requisitar `/api/v1/auth/my-profile` com usuário não verificado e decodificar a resposta JSON como objeto.
2. Observar `role_display_names` como array vazio antes da correção.

## Scope

- Reproducibility: determinística quando o conjunto de papéis é vazio.
- Affected versions/environments: API e testes PHPUnit com SQLite/PostgreSQL.
- Suspected boundary: serialização de `MyProfileResource` e `UserResource`.
