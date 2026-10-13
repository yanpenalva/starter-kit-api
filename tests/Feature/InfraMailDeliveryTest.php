<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SyntheticInfraMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concern\RunsAsyncInfraStack;
use Tests\TestCase;

final class InfraMailDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use RunsAsyncInfraStack;

    public function test_queued_synthetic_mail_is_delivered_by_a_real_worker(): void
    {
        $this->flushMailpit();
        config()->set('queue.default', 'redis');
        config()->set('mail.default', 'smtp');

        $token = (string) Str::uuid();
        Mail::queue(new SyntheticInfraMail($token));

        $worker = $this->runBoundedWorker(tries: 1);
        $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());

        $snapshot = $this->waitForMailpitMessage($token);

        $delivered = null;
        foreach ($snapshot['subjects'] as $subject) {
            if (str_contains($subject, $token)) {
                $delivered = $subject;
                break;
            }
        }

        $this->assertIsString($delivered);
        $this->assertStringStartsWith('[infra-smoke] ', $delivered);
        $this->assertContains('recipient@example.test', $snapshot['recipients']);

        $this->flushMailpit();
    }

    public function test_mailpit_is_actually_reachable_for_integration_tests(): void
    {

        $response = Http::timeout(5)->get($this->mailpitApiUrl().'/api/v1/messages');
        $this->assertTrue($response->successful(), $this->mailpitApiUrl());
    }
}
