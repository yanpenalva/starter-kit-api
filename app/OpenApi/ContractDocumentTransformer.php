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
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

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
        $this->overlay->apply($openApi);
        $this->injectCsrfCookieOverlay($openApi, $cookieName);
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
        $success = Response::make(204);
        $success->setDescription('Sem conteúdo; cookies emitidos através dos cabeçalhos Set-Cookie.');
        $operation->responses = [$success];

        /** @var Path $path */
        $path = Path::make('sanctum/csrf-cookie');
        $path->addOperation($operation);
        $openApi->paths[] = $path;
    }
}
