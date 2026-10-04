<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Applies the real session-cookie security overlay to the generated document.
 *
 * Scramble infers Bearer auth from `auth` middleware by default. This API uses
 * Sanctum stateful session cookies exclusively, so the transformer replaces any
 * bearer scheme with an explicit cookie apiKey scheme plus the XSRF header used
 * on state-changing requests. It also injects the vendor CSRF cookie route,
 * enforces the actual profile schema, and adds the real error contracts.
 */
final class ContractDocumentTransformer
{
    public const LOGICAL_VERSION = '1.0.0';

    public const XSRF_HEADER = 'X-XSRF-TOKEN';

    public function __invoke(OpenApi $openApi): void
    {
        $this->ensureLogicalInfo($openApi);
        $cookieName = $this->registerSessionSchemes($openApi);
        $this->fixProfileSchema($openApi);
        $this->applyOperationOverlays($openApi);
        $this->injectCsrfCookieOverlay($openApi, $cookieName);
    }

    private function ensureLogicalInfo(OpenApi $openApi): void
    {
        $version = trim($openApi->info->version ?? '');
        if ($version === '' || $version === '0.0.1') {
            $openApi->info->version = self::LOGICAL_VERSION;
        }
        if (trim($openApi->info->title ?? '') === '') {
            $openApi->info->title = 'Starter Kit API';
        }
    }

    private function registerSessionSchemes(OpenApi $openApi): string
    {
        $configured = config('session.cookie', 'starter-kit-api-session');
        $configuredName = is_string($configured) ? $configured : 'starter-kit-api-session';
        $cookieName = trim($configuredName) !== '' ? $configuredName : 'starter-kit-api-session';

        foreach (array_keys($openApi->components->securitySchemes) as $name) {
            $scheme = $openApi->components->securitySchemes[$name];
            if ($scheme->type === 'http' || $scheme->type === 'oauth2' || $scheme->type === 'openIdConnect') {
                unset($openApi->components->securitySchemes[$name]);
            }
        }

        /** @var SecurityScheme $sessionScheme */
        $sessionScheme = SecurityScheme::apiKey('cookie', $cookieName);
        $sessionScheme->as('sessionCookie');
        $sessionScheme->setDescription('Sanctum stateful session cookie (HttpOnly, SameSite=lax). First-party SPA only; no Bearer or PAT fallback.');
        $openApi->components->securitySchemes['sessionCookie'] = $sessionScheme;

        /** @var SecurityScheme $xsrfScheme */
        $xsrfScheme = SecurityScheme::apiKey('header', self::XSRF_HEADER);
        $xsrfScheme->as('xsrfToken');
        $xsrfScheme->setDescription('XSRF token header required on state-changing requests; value mirrors the XSRF-TOKEN cookie set by the CSRF endpoint.');
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

    private function applyOperationOverlays(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            $normalized = $this->normalizePath($path->path);
            foreach ($path->operations as $operation) {
                $this->overlayOperation($operation, $normalized);
            }
        }
    }

    private function normalizePath(string $path): string
    {
        return strtolower(trim($path, '/'));
    }

    private function overlayOperation(Operation $operation, string $normalizedPath): void
    {
        $method = strtolower($operation->method);

        if ($this->isLogin($normalizedPath, $method)) {
            $operation->security = [];
            $this->ensureXsrfHeaderParam($operation);
            $this->ensureResponse($operation, $this->messageResponse(419, 'CSRF token mismatch.'));
            $this->ensureResponse($operation, $this->rateLimitedResponse());

            return;
        }

        if ($this->isLogout($normalizedPath, $method)) {
            $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
            $this->ensureXsrfHeaderParam($operation);
            $this->ensureResponse($operation, $this->messageResponse(419, 'CSRF token mismatch.'));
            $this->ensureResponse($operation, $this->rateLimitedResponse());

            return;
        }

        if ($this->isProfile($normalizedPath, $method)) {
            $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
            $this->ensureResponse($operation, $this->messageResponse(403, 'This account has been deactivated.'));
            $this->ensureResponse($operation, $this->rateLimitedResponse());

            return;
        }

        $operation->security = [];
    }

    private function isLogin(string $path, string $method): bool
    {
        return $method === 'post' && str_ends_with($path, 'login');
    }

    private function isLogout(string $path, string $method): bool
    {
        return $method === 'post' && str_ends_with($path, 'logout');
    }

    private function isProfile(string $path, string $method): bool
    {
        return $method === 'get' && str_ends_with($path, 'auth/my-profile');
    }

    private function ensureXsrfHeaderParam(Operation $operation): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->name === self::XSRF_HEADER && $parameter->in === 'header') {
                return;
            }
        }

        /** @var Parameter $param */
        $param = Parameter::make(self::XSRF_HEADER, 'header');
        $param->required = true;
        $param->description = 'XSRF token mirroring the XSRF-TOKEN cookie; required on state-changing requests.';
        /** @var Schema $schema */
        $schema = Schema::fromType(new StringType);
        $param->schema = $schema;
        $operation->parameters[] = $param;
    }

    private function ensureResponse(Operation $operation, Response $response): void
    {
        if ($operation->responses === null) {
            $operation->responses = [];
        }
        foreach ($operation->responses as $index => $existing) {
            if ($existing instanceof Response && (string) $existing->code === (string) $response->code) {
                $operation->responses[$index] = $response;

                return;
            }
        }
        $operation->responses[] = $response;
    }

    private function messageResponse(int $code, string $example): Response
    {
        $type = new ObjectType;
        $message = new StringType;
        $message->examples([$example]);
        $type->addProperty('message', $message);
        $type->setRequired(['message']);

        /** @var Schema $schema */
        $schema = Schema::fromType($type);
        /** @var Response $response */
        $response = Response::make($code);
        $response->setDescription($this->errorDescription($code));
        $response->setContent('application/json', $schema);

        return $response;
    }

    private function rateLimitedResponse(): Response
    {
        $response = $this->messageResponse(429, 'Too many requests.');
        $header = new Header(description: 'Seconds until the client may retry.');
        /** @var Schema $schema */
        $schema = Schema::fromType(new IntegerType);
        $header->schema = $schema;
        $response->addHeader('Retry-After', $header);

        return $response;
    }

    private function errorDescription(int $code): string
    {
        return match ($code) {
            401 => 'Unauthenticated or invalid credentials; never reveals whether an account exists.',
            403 => 'Authenticated but explicitly blocked (deactivated account).',
            419 => 'Missing or invalid CSRF token.',
            429 => 'Rate limit exceeded.',
            default => 'An error.',
        };
    }

    private function injectCsrfCookieOverlay(OpenApi $openApi, string $cookieName): void
    {
        foreach ($openApi->paths as $path) {
            if ($this->normalizePath($path->path) === 'sanctum/csrf-cookie') {
                return;
            }
        }

        /** @var Operation $operation */
        $operation = Operation::make('get');
        $operation->path = 'sanctum/csrf-cookie';
        $operation->setOperationId('sanctum.csrfCookie');
        $operation->tags = ['CSRF'];
        $operation->description = 'Issues the XSRF-TOKEN cookie and the HttpOnly session cookie ('.$cookieName.') for the SPA. Public; no authentication required.';
        $operation->security = [];

        /** @var Response $success */
        $success = Response::make(204);
        $success->setDescription('No content; cookies issued via Set-Cookie headers.');
        $operation->responses = [$success, $this->rateLimitedResponse()];

        /** @var Path $path */
        $path = Path::make('sanctum/csrf-cookie');
        $path->addOperation($operation);
        $openApi->paths[] = $path;
    }
}
