<?php

namespace Modules\Auth\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorRecoveryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('two-factor.recover', $this->token);

        return (new MailMessage)
            ->subject('Recupero 2FA - MioCoach')
            ->greeting("Ciao {$this->user->name},")
            ->line('Hai richiesto la disabilitazione dell\'autenticazione a due fattori perché non hai più accesso al tuo dispositivo.')
            ->line('Cliccando il pulsante qui sotto, il 2FA verrà disabilitato e potrai accedere normalmente.')
            ->action('Disabilita 2FA', $url)
            ->line('Questo link scade tra 24 ore.')
            ->line('Se non hai richiesto questa modifica, ignora questa email. Il tuo account rimane protetto.')
            ->salutation('Il team di MioCoach');
    }
}
