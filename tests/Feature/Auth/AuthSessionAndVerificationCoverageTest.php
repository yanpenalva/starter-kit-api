<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ActiveSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\ApiMessage;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Concern\ActsAsSanctumUser;
use Tests\TestCase;

final class AuthSessionAndVerificationCoverageTest extends TestCase
{
    use ActsAsSanctumUser;
    use RefreshDatabase;

    /** @return array<string, string> */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];
    }

    private function createSessionRow(int $userId, string $id, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'CoverageAgent/1.0',
            'payload' => base64_encode(serialize(['_token' => Str::random(40)])),
            'last_activity' => $lastActivity,
        ]);
    }

    /**
     * ActiveSessionController::index() degrades an unparsable last_activity to
     * null, which the migrated integer column can never produce, so the table is
     * rebuilt with a text column inside the test transaction.
     */
    private function replaceSessionsTableWithTextLastActivity(): void
    {
        Schema::drop('sessions');

        Schema::create('sessions', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->string('last_activity');
        });
    }

    public function test_session_listing_keeps_rows_with_an_unparsable_last_activity(): void
    {
        $this->replaceSessionsTableWithTextLastActivity();

        $user = User::factory()->verified()->create();
        self::actingAsSanctum($user);

        $this->withSession(['listing_probe' => 'present']);
        $currentSessionId = $this->app->make(Store::class)->getId();

        DB::table('sessions')->insert([
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'CoverageAgent/1.0',
            'payload' => base64_encode(serialize(['_token' => Str::random(40)])),
            'last_activity' => 'unknown',
        ]);

        $response = $this->getJson('/api/v1/auth/sessions');

        $response->assertOk()->assertJsonCount(1, 'data');

        $data = $response->json('data');
        $this->assertIsArray($data);

        $row = collect($data)->firstWhere('id', $currentSessionId);
        $this->assertIsArray($row);
        $this->assertArrayHasKey('last_activity', $row);
        $this->assertNull($row['last_activity']);
        $this->assertTrue(($row['is_current'] ?? null) === true);
    }

    public function test_revoking_the_current_session_invalidates_and_rotates_it(): void
    {
        $user = User::factory()->verified()->create();
        self::actingAsSanctum($user);

        $this->withSession(['destroy_probe' => 'present']);
        $session = $this->app->make(Store::class);
        $currentSessionId = $session->getId();

        $this->createSessionRow($user->id, $currentSessionId, Carbon::now()->getTimestamp());

        $cookieName = config('session.cookie');
        $this->assertIsString($cookieName);

        $response = $this->withCredentials()
            ->withHeaders($this->statefulHeaders())
            ->withCookie($cookieName, $currentSessionId)
            ->deleteJson("/api/v1/auth/sessions/{$currentSessionId}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => $currentSessionId]);
        $this->assertNull($session->get('destroy_probe'));
        $this->assertNotSame($currentSessionId, $session->getId());
    }

    public function test_session_listing_without_any_session_context_marks_no_row_as_current(): void
    {
        $user = User::factory()->verified()->create();
        $sessionId = Str::random(40);
        $this->createSessionRow($user->id, $sessionId, Carbon::now()->getTimestamp());

        $request = Request::create('/api/v1/auth/sessions', 'GET');
        $request->setUserResolver(static fn (): User => $user);
        $this->assertFalse($request->hasSession());

        // Without a request session the controller falls back to the container
        // binding; removing it is the only way to reach the defensive null id. The
        // response factory is warmed first so it does not resolve the session store.
        $this->app->make(ResponseFactory::class);
        $this->app->offsetUnset('session');
        $this->assertFalse($this->app->bound('session'));

        $response = (new ActiveSessionController)->index($request);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);

        $data = $payload['data'] ?? null;
        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        $row = $data[0];
        $this->assertIsArray($row);
        $this->assertSame($sessionId, $row['id'] ?? null);
        $this->assertFalse(($row['is_current'] ?? true) === true);
        $this->assertIsString($row['last_activity'] ?? null);
    }

    public function test_verify_email_with_a_non_numeric_identifier_returns_not_found(): void
    {
        $user = User::factory()->unverified()->create();
        self::actingAsSanctum($user);

        $userQueries = [];
        DB::listen(static function (QueryExecuted $query) use (&$userQueries): void {
            if (str_contains($query->sql, 'users')) {
                $userQueries[] = $query->sql;
            }
        });

        $url = URL::temporarySignedRoute('verification.verify', now()->addHours(24), [
            'id' => 'not-a-number',
            'hash' => hash('sha256', $user->email),
        ]);

        $this->getJson($url)
            ->assertNotFound()
            ->assertJsonPath('message', ApiMessage::NOT_FOUND);

        // A malformed identifier must be rejected before any user lookup.
        $this->assertSame([], $userQueries);
        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_verify_email_redirects_to_a_frontend_url_that_already_targets_the_verification_page(): void
    {
        config(['app.frontend_url' => 'http://frontend.test/verify-email?ok=1']);

        $user = User::factory()->unverified()->create();
        self::actingAsSanctum($user);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHours(24), [
            'id' => $user->id,
            'hash' => hash('sha256', $user->email),
        ]);

        $this->get($url)->assertRedirect('http://frontend.test/verify-email?ok=1');

        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertSame(
            'http://frontend.test/verify-email?ok=1',
            VerifyEmailController::frontendVerifyUrl(),
        );
    }

    public function test_register_request_normalizes_the_email_for_lookup(): void
    {
        $request = RegisterRequest::create('/api/v1/register', 'POST', [
            'name' => 'Case Lookup',
            'email' => '  Mixed.Case@Example.TEST  ',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ]);
        $request->setUserResolver(static fn () => null);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        $this->assertSame('mixed.case@example.test', $request->normalizedEmail());

        $request->validateResolved();

        $this->assertSame('mixed.case@example.test', $request->normalizedEmail());
    }

    public function test_profile_update_rejects_a_non_string_email_shape(): void
    {
        $user = User::factory()->verified()->create(['email' => 'shape.profile@example.test']);
        self::actingAsSanctum($user);

        $conflictLookups = [];
        DB::listen(static function (QueryExecuted $query) use (&$conflictLookups): void {
            if (str_contains($query->sql, 'trim(email)')) {
                $conflictLookups[] = $query->sql;
            }
        });

        $this->patchJson('/api/v1/auth/my-profile', ['email' => ['nested' => 'value']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // A non-string shape must fail the type rule and skip the conflict lookup.
        $this->assertSame([], $conflictLookups);
        $this->assertSame('shape.profile@example.test', $user->fresh()?->email);
    }
}
