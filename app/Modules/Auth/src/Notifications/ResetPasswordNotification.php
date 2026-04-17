<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage())
            ->subject('Reset della tua password — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Hai richiesto il reset della tua password.')
            ->action('Reimposta Password', $url)
            ->line('Il link scade tra ' . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . ' minuti.')
            ->line('Se non hai richiesto il reset, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
