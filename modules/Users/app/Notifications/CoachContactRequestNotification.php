<?php

namespace App\Modules\Users\Notifications;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachContactRequest;
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
        private readonly string $type = CoachContactRequest::TYPE_MESSAGE,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName = $this->client->user?->name ?? 'Un cliente';
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $requestsUrl = $frontendUrl.'/coach/contact-requests';

        $isConsultation = $this->type === CoachContactRequest::TYPE_FREE_CONSULTATION;

        $mail = (new MailMessage)
            ->subject($isConsultation
                ? "{$clientName} richiede una consulenza gratuita"
                : "{$clientName} vuole lavorare con te")
            ->greeting("Ciao {$this->coach->user?->name}!")
            ->line($isConsultation
                ? "{$clientName} ti ha chiesto una consulenza conoscitiva gratuita."
                : "{$clientName} ti ha inviato una richiesta di coaching.");

        if ($this->message) {
            $mail->line("Messaggio: \"{$this->message}\"");
        }

        return $mail
            ->action('Visualizza richiesta', $requestsUrl)
            ->line('Puoi accettare o rifiutare la richiesta dalla tua dashboard.');
    }
}
