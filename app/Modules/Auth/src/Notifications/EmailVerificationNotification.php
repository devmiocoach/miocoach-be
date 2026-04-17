<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class EmailVerificationNotification extends VerifyEmail
{
    protected function verificationUrl(mixed $notifiable): string
    {
        return URL::temporarySignedRoute(
            'auth.verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage())
            ->subject('Verifica il tuo indirizzo email — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Clicca il pulsante qui sotto per verificare la tua email.')
            ->action('Verifica Email', $url)
            ->line('Se non hai creato un account MioCoach, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
