<?php

namespace Modules\Bookings\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BookingSource: string implements HasLabel
{
    case Manual = 'manual';
    case PublicWeb = 'public_web';
    case AiBooking = 'ai_booking';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Manual => 'Manual (Coach)',
            self::PublicWeb => 'Public Web',
            self::AiBooking => 'AI Booking',
        };
    }
}
