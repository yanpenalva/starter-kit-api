<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureMfaChallenge;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class AuthGuardCoverageTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    /**
     * The api middleware group only starts a session for stateful requests
     * (Origin/Referer on a Sanctum stateful domain).
     *
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];
    }

    public function test_mfa_challenge_middleware_rejects_a_pipeline_that_returns_no_response(): void
    {
        $middleware = new EnsureMfaChallenge;
        $request = Request::create('/api/v1/auth/my-profile', 'GET');
        $this->assertFalse($request->hasSession());

        $passedThrough = $middleware->handle($request, static fn () => response()->noContent());
        $this->assertSame(SymfonyResponse::HTTP_NO_CONTENT, $passedThrough->getStatusCode());

        try {
            $middleware->handle($request, static fn () => 'not-a-response');
            self::fail('A pipeline value that is not a Response must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The request pipeline must return a response.', $exception->getMessage());
        }
    }

    public function test_login_controller_rejects_a_non_string_password_without_touching_credentials(): void
    {
        $payload = ['email' => 'login.shape@example.test', 'password' => ['nested' => 'value']];

        // Over HTTP the FormRequest `string` rule rejects the payload shape first,
        // so the controller guard is exercised by invoking the controller itself.
        $this->withSession([])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/login', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $request = LoginRequest::create('/api/v1/login', 'POST', $payload);

        try {
            (new LoginController)->__invoke($request);
            self::fail('A non-string password must be rejected with invalid credentials.');
        } catch (HttpException $exception) {
            $this->assertSame(SymfonyResponse::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            $this->assertSame(ApiMessage::INVALID_CREDENTIALS, $exception->getMessage());
        }
    }

    public function test_stateful_logout_invalidates_the_session_and_rotates_its_token(): void
    {
        $user = User::factory()->create();
        self::actingAsSanctum($user);

        $this->withSession(['logout_probe' => 'present'])
            ->withHeaders($this->statefulHeaders());

        $session = $this->app->make(Store::class);
        $this->assertSame('present', $session->get('logout_probe'));
        $tokenBefore = $session->token();
        $idBefore = $session->getId();

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->assertNull($session->get('logout_probe'));
        $this->assertNotSame($tokenBefore, $session->token());
        $this->assertNotSame($idBefore, $session->getId());
    }

    public function test_register_propagates_non_unique_database_failures_and_rolls_back_the_account(): void
    {
        // The controller converts only unique-constraint failures into 422. Dropping
        // the role pivot inside the test transaction fails the role assignment step,
        // which must surface as the generic 500 envelope with no account persisted.
        Schema::drop('model_has_roles');

        $response = $this->withSession([])
            ->withHeaders($this->statefulHeaders())
            ->postJson('/api/v1/register', [
                'name' => 'Synthetic Failure',
                'email' => 'register.failure@example.test',
                'password' => 'correct-horse-12',
                'password_confirmation' => 'correct-horse-12',
            ]);

        $response->assertStatus(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR)
            ->assertExactJson(['message' => ApiMessage::SERVER_ERROR]);

        $this->assertDatabaseMissing('users', ['email' => 'register.failure@example.test']);
    }
}
