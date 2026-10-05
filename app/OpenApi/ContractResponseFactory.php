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
        $response = $this->messageResponse(429, ApiMessage::THROTTLED);
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
            401 => 'Não autenticado ou credenciais inválidas; nunca revela se uma conta existe.',
            403 => 'Autenticado, mas explicitamente bloqueado (conta desativada).',
            419 => 'Token CSRF ausente ou inválido.',
            429 => 'Limite de requisições excedido.',
            default => 'Erro na requisição.',
        };
    }
}
