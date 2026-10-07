<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/v1');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_api_error_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/v1/not-found');

        $response->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_configured_cors_origin_is_allowed_with_credentials_and_foreign_origin_is_denied(): void
    {
        $environment = 'CORS_ALLOWED_ORIGINS';
        $previousEnv = $_ENV[$environment] ?? null;
        $previousServer = $_SERVER[$environment] ?? null;
        $previousEnvironmentValue = is_string($previousEnv) ? $previousEnv : null;
        $previousServerValue = is_string($previousServer) ? $previousServer : null;
        putenv($environment.'=https://spa.example.test');
        $_ENV[$environment] = 'https://spa.example.test';
        $_SERVER[$environment] = 'https://spa.example.test';
        $cors = require config_path('cors.php');
        $_ENV = array_diff_key($_ENV, [$environment => true]);
        $_SERVER = array_diff_key($_SERVER, [$environment => true]);
        $_ENV += $previousEnvironmentValue === null ? [] : [$environment => $previousEnvironmentValue];
        $_SERVER += $previousServerValue === null ? [] : [$environment => $previousServerValue];
        putenv($environment.($previousEnvironmentValue === null ? '' : '='.$previousEnvironmentValue));

        self::assertIsArray($cors);
        self::assertIsArray($cors['allowed_origins']);
        config(['cors.allowed_origins' => $cors['allowed_origins']]);

        $allowed = $this->withHeader('Origin', 'https://spa.example.test')->getJson('/api/v1');

        $allowed->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://spa.example.test')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $foreign = $this->withHeader('Origin', 'https://foreign.example.test')->getJson('/api/v1');

        $foreign->assertOk()
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->assertNotSame(
            'https://foreign.example.test',
            $foreign->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
