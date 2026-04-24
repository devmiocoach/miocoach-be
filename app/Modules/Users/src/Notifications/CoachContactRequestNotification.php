<?php

namespace App\Modules\Users\Notifications;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachContactRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Client $client,
        private readonly Coach $coach,
        private readonly ?string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName  = $this->client->user?->name ?? 'Un cliente';
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $requestsUrl = $frontendUrl . '/coach/contact-requests';

        $mail = (new MailMessage)
            ->subject("{$clientName} vuole lavorare con te")
            ->greeting("Ciao {$this->coach->user?->name}!")
            ->line("{$clientName} ti ha inviato una richiesta di coaching.");

        if ($this->message) {
            $mail->line("Messaggio: \"{$this->message}\"");
        }

        return $mail
            ->action('Visualizza richiesta', $requestsUrl)
            ->line('Puoi accettare o rifiutare la richiesta dalla tua dashboard.');
    }
}
