<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class MfaEnrollmentController
{
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'mfa_enabled' => $user->hasMfaEnabled(),
        ]);
    }

    public function enroll(Request $request, TotpService $totp): JsonResponse
    {
        $user = $this->ensureAdmin($request);

        $secret = $totp->generateSecret();
        $recoveryCodes = $totp->generateRecoveryCodes();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $recoveryCodes,
            'mfa_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $totp->getProvisioningUri($secret, $user->email),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function confirm(Request $request, TotpService $totp): JsonResponse
    {
        $user = $this->ensureAdmin($request);

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $code = $request->string('code')->value();

        if (empty($user->mfa_secret)) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'MFA não iniciado para esta conta.');
        }

        if (! $totp->verify($user->mfa_secret, $code)) {
            return response()->json([
                'message' => 'Código MFA inválido.',
                'errors' => ['code' => ['Código MFA inválido.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill([
            'mfa_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'confirmed' => true,
            'recovery_codes' => $user->mfa_recovery_codes,
        ]);
    }

    public function disable(Request $request): Response
    {
        $user = $this->ensureAdmin($request);

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
        ])->save();

        return response()->noContent();
    }

    public function regenerateRecoveryCodes(Request $request, TotpService $totp): JsonResponse
    {
        $user = $this->ensureAdmin($request);

        if (! $user->hasMfaEnabled()) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'MFA não está ativo nesta conta.');
        }

        $codes = $totp->generateRecoveryCodes();
        $user->forceFill([
            'mfa_recovery_codes' => $codes,
        ])->save();

        return response()->json([
            'recovery_codes' => $codes,
        ]);
    }

    private function ensureAdmin(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->hasRole(Role::Admin->value)) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'Apenas administradores podem gerenciar MFA.');
        }

        return $user;
    }
}
