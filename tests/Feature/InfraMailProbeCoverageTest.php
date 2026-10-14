<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SyntheticInfraMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class InfraMailProbeCoverageTest extends TestCase
{
    public function test_synthetic_mail_envelope_uses_token_subject_and_default_recipient(): void
    {
        config()->set('mail.infra_smoke.recipient', 'recipient@example.test');

        $envelope = (new SyntheticInfraMail('w1b-token-001'))->envelope();

        $this->assertSame('[infra-smoke] w1b-token-001', $envelope->subject);
        $this->assertCount(1, $envelope->to);

        $recipient = $envelope->to[0];
        $this->assertInstanceOf(Address::class, $recipient);
        $this->assertSame('recipient@example.test', $recipient->address);
        $this->assertSame('Infra Smoke', $recipient->name);
    }

    public function test_synthetic_mail_envelope_honours_configured_recipient_and_falls_back_on_empty(): void
    {
        config()->set('mail.infra_smoke.recipient', 'smoke-custom@example.test');
        $configured = (new SyntheticInfraMail('w1b-token-002'))->envelope();

        $configuredRecipient = $configured->to[0];
        $this->assertInstanceOf(Address::class, $configuredRecipient);
        $this->assertSame('smoke-custom@example.test', $configuredRecipient->address);

        config()->set('mail.infra_smoke.recipient', '');
        $fallback = (new SyntheticInfraMail('w1b-token-003'))->envelope();

        $fallbackRecipient = $fallback->to[0];
        $this->assertInstanceOf(Address::class, $fallbackRecipient);
        $this->assertSame('recipient@example.test', $fallbackRecipient->address);
        $this->assertSame('[infra-smoke] w1b-token-003', $fallback->subject);
    }

    public function test_synthetic_mail_content_renders_real_html(): void
    {
        $content = (new SyntheticInfraMail('w1b-token-004'))->content();
        $this->assertSame(
            '<p>Synthetic infrastructure probe message. Safe to ignore.</p>',
            $content->htmlString
        );

        $html = (new SyntheticInfraMail('w1b-token-004'))->render();
        $this->assertStringContainsString('Synthetic infrastructure probe message. Safe to ignore.', $html);
    }

    public function test_smoke_mail_command_queues_synthetic_mail_in_testing(): void
    {
        Mail::fake();

        $exit = Artisan::call('infra:smoke-mail');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertSame(1, preg_match('/enfileirado token=([0-9a-f-]{36})/', $output, $matches), $output);
        $outputToken = $matches[1] ?? throw new \LogicException('Infra smoke output must include its token.');
        $queuedToken = null;
        $envelope = null;

        Mail::assertQueued(SyntheticInfraMail::class, static function (SyntheticInfraMail $mail) use (&$queuedToken, &$envelope): bool {
            $queuedToken = $mail->token;
            $envelope = $mail->envelope();

            return true;
        });

        $this->assertSame($outputToken, $queuedToken);
        $this->assertSame('[infra-smoke] '.$outputToken, $envelope?->subject);
        $this->assertCount(1, $envelope->to);
    }

    public function test_smoke_mail_command_disabled_outside_local_testing_queues_nothing(): void
    {
        Mail::fake();
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $exit = Artisan::call('infra:smoke-mail');
        } finally {
            $this->app['env'] = $previous;
        }

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('desativado fora de local/testing', Artisan::output());
        Mail::assertNothingQueued();
    }

    public function test_probe_command_disabled_outside_local_testing_skips_redis(): void
    {
        Redis::shouldReceive('connection')->never();

        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $exit = Artisan::call('infra:probe', ['--key' => 'infra:probe:w1b-guard']);
        } finally {
            $this->app['env'] = $previous;
        }

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('desativada fora de local/testing', Artisan::output());
    }

    public function test_probe_command_writes_redis_marker(): void
    {
        $key = 'infra:probe:w1b-coverage';
        Redis::connection()->del($key);

        try {
            $exit = Artisan::call('infra:probe', ['--key' => $key]);

            $this->assertSame(0, $exit);
            $this->assertStringContainsString('Sonda Redis OK', Artisan::output());

            $marker = Redis::connection()->get($key);
            $this->assertIsString($marker);
            $this->assertNotSame('', $marker);
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
                $marker,
            );

            $ttl = Redis::connection()->ttl($key);
            $this->assertGreaterThan(0, $ttl);
            $this->assertLessThanOrEqual(300, $ttl);
        } finally {
            Redis::connection()->del($key);
        }
    }
}
