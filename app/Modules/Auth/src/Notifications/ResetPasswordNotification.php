<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        // Il link punta al frontend (Next.js/Flutter) che, dopo la conferma
        // dell'utente, chiamerà POST /api/v1/auth/reset-password con token + email + password.
        $resetUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Reset della tua password — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Hai richiesto il reset della tua password.')
            ->action('Reimposta Password', $resetUrl)
            ->line('Il link scade tra 60 minuti.')
            ->line('Se non hai richiesto il reset, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
