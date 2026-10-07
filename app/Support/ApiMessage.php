<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApiMessage
{
    public const PAGE_EXPIRED = 419;

    public const INVALID_CREDENTIALS = 'Credenciais inválidas.';

    public const UNAUTHENTICATED = 'Não autenticado.';

    public const UNAUTHORIZED = 'Acesso não autorizado.';

    public const DEACTIVATED = 'Esta conta foi desativada.';

    public const NOT_FOUND = 'Recurso não encontrado.';

    public const METHOD_NOT_ALLOWED = 'Método não permitido.';

    public const CSRF = 'Token CSRF inválido ou expirado.';

    public const THROTTLED = 'Muitas tentativas. Por favor, tente novamente mais tarde.';

    public const SERVER_ERROR = 'Erro interno do servidor.';

    public const REQUEST_FAILED = 'Não foi possível concluir a requisição.';

    private const OWNED = [
        self::INVALID_CREDENTIALS,
        self::UNAUTHENTICATED,
        self::UNAUTHORIZED,
        self::DEACTIVATED,
        self::NOT_FOUND,
        self::METHOD_NOT_ALLOWED,
        self::CSRF,
        self::THROTTLED,
    ];

    public static function forHttpException(HttpExceptionInterface $exception): string
    {
        $message = $exception->getMessage();

        if (in_array($message, self::OWNED, true)) {
            return $message;
        }

        return self::forStatus($exception->getStatusCode());
    }

    public static function forStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => self::UNAUTHENTICATED,
            Response::HTTP_FORBIDDEN => self::UNAUTHORIZED,
            Response::HTTP_NOT_FOUND => self::NOT_FOUND,
            Response::HTTP_METHOD_NOT_ALLOWED => self::METHOD_NOT_ALLOWED,
            self::PAGE_EXPIRED => self::CSRF,
            Response::HTTP_TOO_MANY_REQUESTS => self::THROTTLED,
            default => self::REQUEST_FAILED,
        };
    }
}
