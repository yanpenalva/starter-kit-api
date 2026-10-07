<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SessionHandlerInterface;
use Tests\TestCase;

final class SessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'database');

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sessions');

        parent::tearDown();
    }

    public function test_idle_session_beyond_lifetime_is_rejected(): void
    {
        $rawLifetime = config('session.lifetime');
        $this->assertIsInt($rawLifetime);
        $lifetime = $rawLifetime;
        $this->assertGreaterThan(0, $lifetime);

        $id = Str::random(40);
        $this->seedSessionRow($id, Carbon::now()->getTimestamp());

        $this->travel($lifetime + 1)->minutes();

        app()->forgetInstance('session');

        $manager = app('session');
        $this->assertInstanceOf(SessionManager::class, $manager);
        $store = $manager->driver();
        $this->assertInstanceOf(Store::class, $store);
        $handler = $store->getHandler();
        $this->assertInstanceOf(SessionHandlerInterface::class, $handler);

        $this->assertSame('', $handler->read($id));
    }

    public function test_session_within_lifetime_stays_authenticated(): void
    {
        $id = Str::random(40);
        $payload = base64_encode('session-data');
        $this->seedSessionRow($id, Carbon::now()->getTimestamp(), $payload);

        $manager = app('session');
        $this->assertInstanceOf(SessionManager::class, $manager);
        $store = $manager->driver();
        $this->assertInstanceOf(Store::class, $store);
        $handler = $store->getHandler();
        $this->assertInstanceOf(SessionHandlerInterface::class, $handler);

        $this->assertSame('session-data', $handler->read($id));
    }

    public function test_session_lifetime_is_wired_from_configuration(): void
    {
        $rawLifetime = config('session.lifetime');
        $this->assertIsInt($rawLifetime);
        $this->assertSame(120, $rawLifetime);

        $manager = app('session');
        $this->assertInstanceOf(SessionManager::class, $manager);
        $store = $manager->driver();
        $this->assertInstanceOf(Store::class, $store);
        $handler = $store->getHandler();
        $this->assertInstanceOf(DatabaseSessionHandler::class, $handler);
        $minutes = new \ReflectionProperty($handler, 'minutes');
        $wired = $minutes->getValue($handler);
        $this->assertIsInt($wired);

        $this->assertSame($rawLifetime, $wired);
    }

    private function seedSessionRow(string $id, int $lastActivity, string $payload = ''): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => $payload,
            'last_activity' => $lastActivity,
        ]);
    }
}
