<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Support\ApiMessage;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

final class AuthNotificationCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_notification_renders_the_reset_link_with_token_and_recipient(): void
    {
        config([
            'app.frontend_url' => 'http://frontend.test/',
            'app.name' => 'Starter Kit API',
        ]);

        $user = User::factory()->unverified()->create(['email' => 'reset.render@example.test']);

        $mail = (new ResetPasswordNotification('synthetic-token-123'))->toMail($user);

        $this->assertSame('Redefinição de senha', $mail->subject);
        $this->assertSame('Olá!', $mail->greeting);
        $this->assertContains(
            'Você está recebendo este email porque solicitou a redefinição da sua senha.',
            $mail->introLines,
        );
        $this->assertSame('Redefinir senha', $mail->actionText);
        $this->assertSame(
            'http://frontend.test/reset-password?token=synthetic-token-123&email=reset.render%40example.test',
            $mail->actionUrl,
        );
        $this->assertContains('Este link expira em 60 minutos.', $mail->outroLines);
        $this->assertContains('Se você não solicitou a redefinição, nenhuma ação é necessária.', $mail->outroLines);
        $this->assertSame('Atenciosamente, Starter Kit API', $mail->salutation);

        $this->assertStringContainsString(
            'http://frontend.test/reset-password?token=synthetic-token-123',
            (string) $mail->render(),
        );
    }

    /**
     * Laravel skips CSRF verification while the app runs in console (in-process
     * tests), so the 419 render path is driven through the application exception
     * handler with the same exception the production middleware throws.
     */
    public function test_unowned_419_http_exception_renders_the_public_csrf_message(): void
    {
        $request = Request::create('/api/v1/logout', 'POST');

        $response = $this->app->make(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException('CSRF token mismatch.'),
        );

        $this->assertSame(ApiMessage::PAGE_EXPIRED, $response->getStatusCode());
        $this->assertSame(
            ['message' => ApiMessage::CSRF],
            json_decode((string) $response->getContent(), true),
        );
    }
}
