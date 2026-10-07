<?php

namespace Modules\Checkins\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Checkins\Models\CheckinResponse;

class ClientSubmittedCheckinNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CheckinResponse $response,
        public string $clientName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'checkin_submitted',
            'title' => "{$this->clientName} ha compilato il check-in",
            'checkinFormId' => $this->response->checkin_form_id,
            'checkinResponseId' => $this->response->id,
            'clientId' => $this->response->client_id,
        ];
    }
}
