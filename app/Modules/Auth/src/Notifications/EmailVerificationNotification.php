<?php

namespace App\Modules\Auth\Notifications;

use App\Modules\Auth\Services\JwtService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $token = app(JwtService::class)->generateEmailVerifyToken($notifiable);

        $verifyUrl = rtrim(config('app.url'), '/') . '/api/v1/auth/verify-email/' . $token;

        return (new MailMessage())
            ->subject('Verifica il tuo indirizzo email — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Clicca il pulsante qui sotto per verificare la tua email.')
            ->action('Verifica Email', $verifyUrl)
            ->line('Il link scade tra 24 ore.')
            ->line('Se non hai creato un account MioCoach, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
