<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Single owner of every human-facing API message (pt-BR language policy).
 * Machine interfaces (HTTP status codes, JSON keys, routes) stay unchanged.
 */
final class ApiMessage
{
    public const INVALID_CREDENTIALS = 'Credenciais inválidas.';

    public const UNAUTHENTICATED = 'Não autenticado.';

    public const UNAUTHORIZED = 'Acesso não autorizado.';

    public const DEACTIVATED = 'Esta conta foi desativada.';

    public const NOT_FOUND = 'Recurso não encontrado.';

    public const METHOD_NOT_ALLOWED = 'Método não permitido.';

    public const CSRF = 'Token CSRF inválido ou expirado.';

    public const THROTTLED = 'Muitas tentativas. Por favor, tente novamente mais tarde.';

    public const SERVER_ERROR = 'Erro interno do servidor.';

    /**
     * Messages this application deliberately aborts with. Only these are
     * trusted verbatim; any other upstream exception text (framework
     * defaults like "This action is unauthorized." or "CSRF token
     * mismatch.", model-binding details) is replaced by the localized
     * status message so English internals never leak into API bodies.
     */
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

        return self::forStatus($exception->getStatusCode(), $message);
    }

    public static function forStatus(int $status, string $fallback = ''): string
    {
        return match ($status) {
            401 => self::UNAUTHENTICATED,
            403 => self::UNAUTHORIZED,
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            419 => self::CSRF,
            429 => self::THROTTLED,
            default => $fallback !== '' ? $fallback : self::SERVER_ERROR,
        };
    }
}
