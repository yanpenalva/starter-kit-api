<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\EmailLookup;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ResetPasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{email: string, token: string, password: string} $validated */
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $stored = EmailLookup::find(EmailLookup::normalize($validated['email']));

        $storedEmail = $stored === null ? EmailLookup::normalize($validated['email']) : $stored->email;

        $status = Password::broker()->reset(
            [
                'email' => $storedEmail,
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            static function (CanResetPasswordContract $user, string $password): void {
                if ($user instanceof User) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    DB::table('sessions')
                        ->where('user_id', $user->getKey())
                        ->delete();

                    event(new PasswordReset($user));
                }
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $message = match ($status) {
                Password::RESET_THROTTLED => (string) trans('passwords.throttled'),
                default => (string) trans('passwords.token'),
            };

            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return response()->json(['message' => (string) trans('passwords.reset')], Response::HTTP_OK);
    }
}
