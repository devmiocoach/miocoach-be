<?php

namespace Modules\Checkins\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Checkins\Models\CheckinForm;

class CheckinDueNotification extends Notification
{
    use Queueable;

    public function __construct(public CheckinForm $form) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'checkin_due',
            'title' => "È ora del tuo check-in: {$this->form->title}",
            'checkinFormId' => $this->form->id,
        ];
    }
}
