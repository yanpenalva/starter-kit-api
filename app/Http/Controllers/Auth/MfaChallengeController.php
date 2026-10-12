<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\MfaRecoveryCodeService;
use App\Services\TotpService;
use App\Support\BlockedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class MfaChallengeController
{
    public function __invoke(
        Request $request,
        TotpService $totp,
        MfaRecoveryCodeService $recoveryCodeService,
    ): Response|JsonResponse {
        $userId = $request->session()->get('mfa:user_id');

        if (! is_int($userId)) {
            abort(SymfonyResponse::HTTP_UNAUTHORIZED, 'Sessão de desafio MFA não encontrada ou expirada.');
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if ($user === null || ! $user->hasMfaEnabled()) {
            abort(SymfonyResponse::HTTP_UNAUTHORIZED, 'Usuário inválido para o desafio MFA.');
        }

        if ($user->deactivated_at !== null && $user->hasVerifiedEmail()) {
            $request->session()->forget('mfa:user_id');

            return BlockedAccount::response($user);
        }

        return $this->completeChallenge($request, $user, $totp, $recoveryCodeService);
    }

    private function completeChallenge(
        Request $request,
        User $user,
        TotpService $totp,
        MfaRecoveryCodeService $recoveryCodeService,
    ): Response|JsonResponse {
        $code = $request->input('code');
        $recoveryCodeInput = $request->input('recovery_code');

        if (! is_string($code) && ! is_string($recoveryCodeInput)) {
            return response()->json([
                'message' => 'Informe o código MFA ou um código de recuperação.',
                'errors' => ['code' => ['Código MFA ou de recuperação obrigatório.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $passed = false;

        if (is_string($code) && ! empty($user->mfa_secret)) {
            $passed = $totp->verify($user->mfa_secret, trim($code));
        }

        if (! $passed && is_string($recoveryCodeInput)) {
            $passed = $recoveryCodeService->consume($user->id, Str::upper(trim($recoveryCodeInput)));
        }

        if (! $passed) {
            return response()->json([
                'message' => 'Código MFA ou de recuperação inválido.',
                'errors' => ['code' => ['Código MFA ou de recuperação inválido.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        Auth::guard('web')->login($user);

        $request->session()->forget('mfa:user_id');
        $request->session()->put('mfa:verified_user_id', $user->id);
        $request->session()->regenerate();

        return response()->noContent();
    }
}
