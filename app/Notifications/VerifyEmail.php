<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 60];

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $signed = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            [
                'id' => $notifiable->getKey(),
                'hash' => hash('sha256', (string) $notifiable->getEmailForVerification()),
            ]
        );

        $parts = parse_url($signed);
        $query = [];
        if (is_array($parts) && isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        $base = rtrim(config()->string('app.frontend_url', 'http://localhost:5173'), '/');
        $url = $base.'/verify-email?'.http_build_query(array_merge([
            'id' => $notifiable->getKey(),
            'hash' => hash('sha256', (string) $notifiable->getEmailForVerification()),
        ], $query));

        return (new MailMessage)
            ->subject('Verifique seu endereço de email')
            ->greeting('Olá!')
            ->line('Clique no botão abaixo para verificar seu endereço de email.')
            ->action('Verificar email', $url)
            ->line('Este link expira em 24 horas.')
            ->salutation('Atenciosamente, '.config()->string('app.name', 'Starter Kit API'));
    }
}
