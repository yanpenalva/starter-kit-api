<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ContractDocumentTransformer
{
    public const LOGICAL_VERSION = '1.0.0';

    public const XSRF_HEADER = 'X-XSRF-TOKEN';

    private ContractOperationOverlay $overlay;

    public function __construct(?ContractOperationOverlay $overlay = null)
    {
        $this->overlay = $overlay ?? new ContractOperationOverlay;
    }

    public function __invoke(OpenApi $openApi): void
    {
        $this->ensureLogicalInfo($openApi);
        $this->ensureServers($openApi);
        $cookieName = $this->registerSessionSchemes($openApi);
        $this->fixProfileSchema($openApi);
        $this->fixResourceDateTimeSchemas($openApi);
        $this->fixResourceArraySchemas($openApi);
        $this->overlay->apply($openApi);
        $this->injectCsrfCookieOverlay($openApi, $cookieName);
        $this->fixPaginatedListResponses($openApi);
    }

    private function ensureLogicalInfo(OpenApi $openApi): void
    {
        $version = trim($openApi->info->version ?? '');
        if ($version === '' || $version === '0.0.1') {
            $openApi->info->version = self::LOGICAL_VERSION;
        }
        $openApi->info->title = 'Starter Kit API';
    }

    private function ensureServers(OpenApi $openApi): void
    {
        /** @var Server $server */
        $server = Server::make('/');
        $openApi->servers = [$server];
        foreach ($openApi->paths as $path) {
            $path->servers = [];
            foreach ($path->operations as $operation) {
                $operation->servers([]);
            }
        }
    }

    private function registerSessionSchemes(OpenApi $openApi): string
    {
        $configured = config('session.cookie', 'starter-kit-api-session');
        $configuredName = is_string($configured) ? $configured : 'starter-kit-api-session';
        $cookieName = trim($configuredName) !== '' ? $configuredName : 'starter-kit-api-session';
        if ($cookieName === 'laravel-session') {
            $cookieName = 'starter-kit-api-session';
        }

        foreach (array_keys($openApi->components->securitySchemes) as $name) {
            $scheme = $openApi->components->securitySchemes[$name];
            if ($scheme->type === 'http' || $scheme->type === 'oauth2' || $scheme->type === 'openIdConnect') {
                unset($openApi->components->securitySchemes[$name]);
            }
        }

        /** @var SecurityScheme $sessionScheme */
        $sessionScheme = SecurityScheme::apiKey('cookie', $cookieName);
        $sessionScheme->as('sessionCookie');
        $sessionScheme->setDescription('Cookie de sessão stateful do Sanctum (HttpOnly, SameSite=lax). Apenas SPA primária; sem fallback para Bearer ou PAT.');
        $openApi->components->securitySchemes['sessionCookie'] = $sessionScheme;

        /** @var SecurityScheme $xsrfScheme */
        $xsrfScheme = SecurityScheme::apiKey('header', self::XSRF_HEADER);
        $xsrfScheme->as('xsrfToken');
        $xsrfScheme->setDescription('Cabeçalho do token XSRF obrigatório em requisições que alteram estado; o valor espelha o cookie XSRF-TOKEN emitido pelo endpoint CSRF.');
        $openApi->components->securitySchemes['xsrfToken'] = $xsrfScheme;

        $openApi->security = [];

        return $cookieName;
    }

    private function fixProfileSchema(OpenApi $openApi): void
    {
        $openApi->components->schemas['MyProfileResource'] = $this->profileSchema();
    }

    private function profileSchema(): Schema
    {
        $profile = new ObjectType;
        $profile->addProperty('id', new IntegerType);
        $profile->addProperty('name', new StringType);
        $email = new StringType;
        $email->format('email');
        $profile->addProperty('email', $email);
        $verifiedAt = new StringType;
        $verifiedAt->format('date-time');
        $verifiedAt->nullable(true);
        $profile->addProperty('email_verified_at', $verifiedAt);
        $roles = new ArrayType;
        $roles->setItems(new StringType);
        $profile->addProperty('roles', $roles);
        $permissions = new ArrayType;
        $permissions->setItems(new StringType);
        $profile->addProperty('permissions', $permissions);
        $profile->setRequired(['id', 'name', 'email', 'email_verified_at', 'roles', 'permissions']);

        /** @var Schema $schema */
        $schema = Schema::fromType($profile);

        return $schema;
    }

    private function fixResourceDateTimeSchemas(OpenApi $openApi): void
    {
        $fields = [
            'UserResource' => ['email_verified_at', 'deactivated_at'],
            'ActivityResource' => ['created_at'],
        ];

        foreach ($fields as $schemaName => $propertyNames) {
            $schema = $openApi->components->schemas[$schemaName] ?? null;
            if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType) {
                continue;
            }

            foreach ($propertyNames as $propertyName) {
                if (! $schema->type->hasProperty($propertyName)) {
                    continue;
                }

                $property = $schema->type->getProperty($propertyName);
                if ($property instanceof StringType) {
                    $property->format('date-time')->nullable(true);
                }
            }
        }
    }

    private function fixResourceArraySchemas(OpenApi $openApi): void
    {
        foreach (['RoleResource' => 'permissions', 'UserResource' => 'roles'] as $schemaName => $propertyName) {
            $schema = $openApi->components->schemas[$schemaName] ?? null;
            if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType || ! $schema->type->hasProperty($propertyName)) {
                continue;
            }

            $property = $schema->type->getProperty($propertyName);
            if ($property instanceof ArrayType) {
                $property->setItems(new StringType);
            }
        }

        $userSchema = $openApi->components->schemas['UserResource'] ?? null;
        if (! $userSchema instanceof Schema || ! $userSchema->type instanceof ObjectType || ! $userSchema->type->hasProperty('cpf')) {
            return;
        }

        $cpf = $userSchema->type->getProperty('cpf');
        if ($cpf instanceof StringType) {
            $cpf->nullable(true);
        }
    }

    private function fixPaginatedListResponses(OpenApi $openApi): void
    {
        $paginatedPaths = ['api/v1/users', 'api/v1/roles', 'api/v1/activity-log'];

        foreach ($openApi->paths as $path) {
            if (! in_array(strtolower(trim($path->path, '/')), $paginatedPaths, true)) {
                continue;
            }

            foreach ($path->operations as $operation) {
                if (strtolower($operation->method) !== 'get') {
                    continue;
                }

                foreach ($operation->responses ?? [] as $response) {
                    if (! $response instanceof Response || (string) $response->code !== '200') {
                        continue;
                    }

                    $schema = $response->content['application/json'] ?? null;
                    if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType || ! $schema->type->hasProperty('data')) {
                        continue;
                    }

                    $schema->type->addProperty('links', $this->paginationLinksSchema());
                    $schema->type->addProperty('meta', $this->paginationMetaSchema());
                    $schema->type->addRequired(['links', 'meta']);
                }
            }
        }
    }

    private function paginationLinksSchema(): ObjectType
    {
        $links = new ObjectType;
        $links->addProperty('first', new StringType);
        $links->addProperty('last', new StringType);

        $previous = new StringType;
        $previous->nullable(true);
        $links->addProperty('prev', $previous);

        $next = new StringType;
        $next->nullable(true);
        $links->addProperty('next', $next);
        $links->setRequired(['first', 'last', 'prev', 'next']);

        return $links;
    }

    private function paginationMetaSchema(): ObjectType
    {
        $meta = new ObjectType;
        $meta->addProperty('current_page', new IntegerType);

        $from = new IntegerType;
        $from->nullable(true);
        $meta->addProperty('from', $from);
        $meta->addProperty('last_page', new IntegerType);

        $pageLinks = new ArrayType;
        $pageLink = new ObjectType;
        $url = new StringType;
        $url->nullable(true);
        $pageLink->addProperty('url', $url);
        $pageLink->addProperty('label', new StringType);
        $pageLink->addProperty('active', new BooleanType);
        $page = new IntegerType;
        $page->nullable(true);
        $pageLink->addProperty('page', $page);
        $pageLink->setRequired(['url', 'label', 'active', 'page']);
        $pageLinks->setItems($pageLink);
        $meta->addProperty('links', $pageLinks);

        $meta->addProperty('path', new StringType);
        $meta->addProperty('per_page', new IntegerType);

        $to = new IntegerType;
        $to->nullable(true);
        $meta->addProperty('to', $to);
        $meta->addProperty('total', new IntegerType);
        $meta->setRequired(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total']);

        return $meta;
    }

    private function injectCsrfCookieOverlay(OpenApi $openApi, string $cookieName): void
    {
        foreach ($openApi->paths as $path) {
            if (strtolower(trim($path->path, '/')) === 'sanctum/csrf-cookie') {
                return;
            }
        }

        /** @var Operation $operation */
        $operation = Operation::make('get');
        $operation->path = 'sanctum/csrf-cookie';
        $operation->setOperationId('sanctum.csrfCookie');
        $operation->tags = ['CSRF'];
        $operation->description = 'Emite o cookie XSRF-TOKEN e o cookie de sessão HttpOnly ('.$cookieName.') para a SPA. Público; nenhuma autenticação necessária.';
        $operation->security = [];

        /** @var Response $success */
        $success = Response::make(HttpResponse::HTTP_NO_CONTENT);
        $success->setDescription('Sem conteúdo; cookies emitidos através dos cabeçalhos Set-Cookie.');
        $operation->responses = [$success];

        /** @var Path $path */
        $path = Path::make('sanctum/csrf-cookie');
        $path->addOperation($operation);
        $openApi->paths[] = $path;
    }
}
