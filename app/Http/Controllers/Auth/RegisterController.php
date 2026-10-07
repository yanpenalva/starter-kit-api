<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RegisterController
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, cpf: string|null} $validated */
        $validated = $request->validated();

        try {
            $user = DB::transaction(static fn (): User => User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'cpf' => $validated['cpf'] ?? null,
                'email_verified_at' => null,
                'deactivated_at' => null,
            ]));
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                $this->conflictingIdentityField($exception) => $this->conflictingIdentityMessage($exception),
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Sua conta foi criada, mas não foi possível enviar o e-mail de verificação. Entre e solicite um novo envio.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
        ], Response::HTTP_CREATED);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) && is_int($errorInfo[1] ?? null) ? $errorInfo[1] : null;

        return $exception->getCode() === '23505'
            || ($exception->getCode() === '23000' && $driverCode === 19);
    }

    private function conflictingIdentityField(QueryException $exception): string
    {
        $driverMessage = $exception->getPrevious()?->getMessage() ?? '';

        return preg_match('/(?:users\.)?cpf|\(cpf\)=/i', $driverMessage) === 1 ? 'cpf' : 'email';
    }

    private function conflictingIdentityMessage(QueryException $exception): string
    {
        return match ($this->conflictingIdentityField($exception)) {
            'cpf' => 'Este CPF já está cadastrado.',
            default => 'Este e-mail já está cadastrado. Tente entrar ou recuperar sua senha.',
        };
    }
}
