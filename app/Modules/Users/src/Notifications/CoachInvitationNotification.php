<?php

namespace App\Modules\Users\Notifications;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CoachInvitation $invitation,
        private readonly Coach $coach,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $coachName   = $this->coach->user->name;
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $registerUrl = $frontendUrl . '/register/invite/' . $this->invitation->token;

        return (new MailMessage)
            ->subject("{$coachName} ti ha invitato su MioCoach")
            ->greeting("Ciao!")
            ->line("{$coachName} ti ha invitato a unirti a MioCoach come suo cliente.")
            ->action('Accetta l\'invito', $registerUrl)
            ->line("Il link scadrà il " . $this->invitation->expires_at->format('d/m/Y alle H:i') . '.')
            ->line('Se non hai richiesto questo invito, puoi ignorare questa email.');
    }
}
