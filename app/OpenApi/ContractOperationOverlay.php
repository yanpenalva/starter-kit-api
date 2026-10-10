<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Support\ApiMessage;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ContractOperationOverlay
{
    private ContractResponseFactory $responses;

    public function __construct(?ContractResponseFactory $responses = null)
    {
        $this->responses = $responses ?? new ContractResponseFactory;
    }

    public function apply(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            $normalized = $this->normalizePath($path->path);
            foreach ($path->operations as $operation) {
                $this->overlayOperation($operation, $normalized);
            }
        }
    }

    private function overlayOperation(Operation $operation, string $normalizedPath): void
    {
        $method = strtolower($operation->method);

        if ($this->isLogin($normalizedPath, $method)) {
            $this->applyLogin($operation);

            return;
        }

        if ($this->isLogout($normalizedPath, $method)) {
            $this->applyLogout($operation);

            return;
        }

        if ($this->isProfile($normalizedPath, $method)) {
            $this->applyProfile($operation);

            return;
        }

        if ($this->isRegister($normalizedPath, $method)) {
            $this->applyRegister($operation);

            return;
        }

        if ($this->isVerificationSend($normalizedPath, $method)) {
            $this->applyVerificationSend($operation);

            return;
        }

        if ($this->isVerify($normalizedPath, $method)) {
            $this->applyVerify($operation);

            return;
        }

        if ($this->isForgotPassword($normalizedPath, $method)) {
            $this->applyForgotPassword($operation);

            return;
        }

        if ($this->isResetPassword($normalizedPath, $method)) {
            $this->applyResetPassword($operation);

            return;
        }

        $operation->security = [];
    }

    private function applyLogin(Operation $operation): void
    {
        $operation->security = [];
        $this->ensureXsrfHeaderParam($operation);
        $this->ensureResponse($operation, $this->responses->messageResponse(HttpResponse::HTTP_UNAUTHORIZED, ApiMessage::INVALID_CREDENTIALS));
        $this->ensureResponse($operation, $this->responses->blockedResponse(HttpResponse::HTTP_FORBIDDEN, ApiMessage::DEACTIVATED));
        $this->ensureResponse($operation, $this->responses->messageResponse(ApiMessage::PAGE_EXPIRED, ApiMessage::CSRF));
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function applyLogout(Operation $operation): void
    {
        $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
        $this->ensureXsrfHeaderParam($operation);
        $this->ensureResponse($operation, $this->responses->messageResponse(HttpResponse::HTTP_UNAUTHORIZED, ApiMessage::UNAUTHENTICATED));
        $this->ensureResponse($operation, $this->responses->messageResponse(ApiMessage::PAGE_EXPIRED, ApiMessage::CSRF));
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function applyProfile(Operation $operation): void
    {
        $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
        $this->ensureResponse($operation, $this->responses->messageResponse(HttpResponse::HTTP_UNAUTHORIZED, ApiMessage::UNAUTHENTICATED));
        $this->ensureResponse($operation, $this->responses->blockedResponse(HttpResponse::HTTP_FORBIDDEN, ApiMessage::DEACTIVATED));
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function applyRegister(Operation $operation): void
    {
        $operation->security = [];
        $this->ensureXsrfHeaderParam($operation);
        $this->ensureResponse($operation, $this->responses->messageResponse(ApiMessage::PAGE_EXPIRED, ApiMessage::CSRF));
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function applyVerificationSend(Operation $operation): void
    {
        $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
        $this->ensureResponse($operation, $this->responses->messageResponse(HttpResponse::HTTP_UNAUTHORIZED, ApiMessage::UNAUTHENTICATED));
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function applyVerify(Operation $operation): void
    {
        $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
        $this->ensureResponse($operation, $this->responses->messageResponse(HttpResponse::HTTP_UNAUTHORIZED, ApiMessage::UNAUTHENTICATED));
        $this->ensureResponse($operation, $this->responses->redirectResponse());
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
        $this->ensureQueryParam($operation, 'expires', 'Carimbo de expiração da URL assinada; exigido pelo middleware de assinatura.');
        $this->ensureQueryParam($operation, 'signature', 'Assinatura HMAC da URL de verificação; exigido pelo middleware de assinatura.');
    }

    private function applyForgotPassword(Operation $operation): void
    {
        $operation->security = [];
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function applyResetPassword(Operation $operation): void
    {
        $operation->security = [];
        $this->ensureResponse($operation, $this->responses->rateLimitedResponse());
    }

    private function ensureXsrfHeaderParam(Operation $operation): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->name === ContractDocumentTransformer::XSRF_HEADER && $parameter->in === 'header') {
                return;
            }
        }

        /** @var Parameter $param */
        $param = Parameter::make(ContractDocumentTransformer::XSRF_HEADER, 'header');
        $param->required = true;
        $param->description = 'Token XSRF espelhando o cookie XSRF-TOKEN; obrigatório em requisições que alteram estado.';
        /** @var Schema $schema */
        $schema = Schema::fromType(new StringType);
        $param->schema = $schema;
        $operation->parameters[] = $param;
    }

    private function ensureQueryParam(Operation $operation, string $name, string $description): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->name === $name && $parameter->in === 'query') {
                return;
            }
        }

        /** @var Parameter $param */
        $param = Parameter::make($name, 'query');
        $param->required = false;
        $param->description = $description;
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

    private function normalizePath(string $path): string
    {
        return strtolower(trim($path, '/'));
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

    private function isRegister(string $path, string $method): bool
    {
        return $method === 'post' && str_ends_with($path, 'register');
    }

    private function isVerificationSend(string $path, string $method): bool
    {
        return $method === 'post' && str_ends_with($path, 'email/verification-notification');
    }

    private function isVerify(string $path, string $method): bool
    {
        return $method === 'get' && str_contains($path, 'email/verify/');
    }

    private function isForgotPassword(string $path, string $method): bool
    {
        return $method === 'post' && str_ends_with($path, 'forgot-password');
    }

    private function isResetPassword(string $path, string $method): bool
    {
        return $method === 'post' && str_ends_with($path, 'reset-password');
    }
}
