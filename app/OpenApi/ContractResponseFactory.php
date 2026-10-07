<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Support\ApiMessage;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ContractResponseFactory
{
    public function messageResponse(int $code, string $example): Response
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

    public function rateLimitedResponse(): Response
    {
        $response = $this->messageResponse(HttpResponse::HTTP_TOO_MANY_REQUESTS, ApiMessage::THROTTLED);
        $header = new Header(description: 'Segundos até que o cliente possa tentar novamente.');
        /** @var Schema $schema */
        $schema = Schema::fromType(new IntegerType);
        $header->schema = $schema;
        $response->addHeader('Retry-After', $header);

        return $response;
    }

    public function errorDescription(int $code): string
    {
        return match ($code) {
            HttpResponse::HTTP_UNAUTHORIZED => 'Não autenticado ou credenciais inválidas; nunca revela se uma conta existe.',
            HttpResponse::HTTP_FORBIDDEN => 'Autenticado, mas explicitamente bloqueado (conta desativada).',
            ApiMessage::PAGE_EXPIRED => 'Token CSRF ausente ou inválido.',
            HttpResponse::HTTP_TOO_MANY_REQUESTS => 'Limite de requisições excedido.',
            default => 'Erro na requisição.',
        };
    }
}
