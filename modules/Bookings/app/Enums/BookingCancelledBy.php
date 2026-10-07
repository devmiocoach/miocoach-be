<?php

namespace Modules\Bookings\Enums;

enum BookingCancelledBy: string
{
    case Coach = 'coach';
    case Client = 'client';
}
