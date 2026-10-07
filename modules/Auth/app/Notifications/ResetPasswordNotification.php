<?php

namespace Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The reset link differs per surface: the web flow issues a password broker
 * token consumed by an Inertia page, while the API flow issues a JWT consumed
 * by the client app. The caller decides which URL to send.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(
        private readonly string $resetUrl,
    ) {}

    /**
     * Link to the Inertia reset page, which validates password broker tokens.
     */
    public static function forWeb(string $token, string $email): self
    {
        return new self(route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]));
    }

    /**
     * Link to the client app, which posts the JWT to /api/v1/auth/reset-password.
     */
    public static function forClientApp(string $token, string $email): self
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');

        return new self($baseUrl.'/reset-password?token='.urlencode($token).'&email='.urlencode($email));
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset della tua password — MioCoach')
            ->greeting('Ciao '.$notifiable->name.',')
            ->line('Hai richiesto il reset della tua password.')
            ->action('Reimposta Password', $this->resetUrl)
            ->line('Il link scade tra 60 minuti.')
            ->line('Se non hai richiesto il reset, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
