<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Dev-only synthetic probe mail for async infrastructure smoke tests.
 * Always delivered to the reserved `.test` recipient from config; the
 * token is a random UUID and the body is generic on purpose — no real
 * recipient, no credentials, no PII.
 */
final class SyntheticInfraMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $token) {}

    public function envelope(): Envelope
    {
        $configured = config('mail.infra_smoke.recipient');
        $recipient = is_string($configured) && $configured !== '' ? $configured : 'recipient@example.test';

        return new Envelope(
            to: [new Address($recipient, 'Infra Smoke')],
            subject: sprintf('[infra-smoke] %s', $this->token),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Synthetic infrastructure probe message. Safe to ignore.</p>',
        );
    }
}
