<?php

namespace App\Modules\Users\Enums;

enum CoachActivityType: string
{
    case NewBooking = 'new_booking';
    case SessionCompleted = 'session_completed';
    case PaymentReceived = 'payment_received';
    case NewClient = 'new_client';
    case CheckinCompleted = 'checkin_completed';
}
