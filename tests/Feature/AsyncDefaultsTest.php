<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

final class AsyncDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_queue_and_log_mail_are_the_local_defaults(): void
    {
        config()->set('queue.default', 'database');
        config()->set('mail.default', 'log');

        $this->assertSame('database', config('queue.default'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame('log', config('mail.default'));
    }

    public function test_identity_endpoints_queue_notifications_and_keep_generic_responses(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create(['email' => 'async-user@example.test']);

        $this->withSession([])->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/register', [
            'name' => 'Async User',
            'email' => 'async-new@example.test',
            'password' => 'correct-horse-12',
            'password_confirmation' => 'correct-horse-12',
        ])->assertCreated();

        $this->withSession([])->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertAccepted()
            ->assertExactJson(['message' => 'Se o email existir, enviamos o link.']);

        $this->actingAs($user, 'web')->withSession([])->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/email/verification-notification')
            ->assertAccepted()
            ->assertExactJson(['message' => 'Se o email ainda não foi verificado, reenviamos o link.']);

        Queue::assertPushed(SendQueuedNotifications::class, 3);
        Queue::assertPushed(SendQueuedNotifications::class, static function (SendQueuedNotifications $job): bool {
            return $job->notification instanceof VerifyEmail || $job->notification instanceof ResetPassword;
        });
    }

    public function test_identity_notifications_define_three_attempts_and_progressive_backoff(): void
    {
        $verify = new VerifyEmail;
        $reset = new ResetPassword('synthetic-token');

        $this->assertSame(3, $verify->tries);
        $this->assertSame([5, 15, 60], $verify->backoff);
        $this->assertSame(3, $reset->tries);
        $this->assertSame([5, 15, 60], $reset->backoff);
    }

    public function test_daily_pruning_commands_are_registered(): void
    {
        $events = Schedule::events();
        $expressions = [];
        foreach ($events as $event) {
            $command = (string) $event->command;
            foreach (['queue:prune-failed --hours=168', 'auth:prune-expired-tokens'] as $signature) {
                if (str_contains($command, $signature)) {
                    $expressions[$signature] = $event->expression;
                }
            }
        }

        $this->assertSame('0 0 * * *', $expressions['queue:prune-failed --hours=168'] ?? null);
        $this->assertSame('0 0 * * *', $expressions['auth:prune-expired-tokens'] ?? null);
    }

    public function test_expired_reset_tokens_and_unverified_accounts_are_pruned(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        DB::table('password_reset_tokens')->insert([
            ['email' => 'expired@example.test', 'token' => 'expired', 'created_at' => now()->subMinutes(61)],
            ['email' => 'valid@example.test', 'token' => 'valid', 'created_at' => now()->subMinutes(59)],
        ]);
        $expired = User::factory()->unverified()->create([
            'email' => 'verification-expired@example.test',
            'created_at' => now()->subHours(25),
        ]);
        $valid = User::factory()->unverified()->create([
            'email' => 'verification-valid@example.test',
            'created_at' => now()->subHours(23),
        ]);

        $exitCode = Artisan::call('auth:prune-expired-tokens');

        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'expired@example.test']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'valid@example.test']);
        $this->assertDatabaseMissing('users', ['id' => $expired->id]);
        $this->assertDatabaseHas('users', ['id' => $valid->id]);
    }
}
