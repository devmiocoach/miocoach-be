<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDeviceLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $ip,
        private readonly string $userAgent,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Nuovo accesso al tuo account — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('È stato rilevato un accesso al tuo account da un nuovo dispositivo.')
            ->line('**IP:** ' . $this->ip)
            ->line('**Dispositivo:** ' . $this->userAgent)
            ->line('**Orario:** ' . now()->format('d/m/Y H:i'))
            ->line('Se sei stato tu, puoi ignorare questa email.')
            ->action('Proteggi il tuo account', url('/'))
            ->salutation('Il team MioCoach');
    }
}
