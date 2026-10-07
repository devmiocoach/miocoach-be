<?php

namespace Modules\Checkins\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CheckinFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Biweekly => __('Biweekly'),
            self::Monthly => __('Monthly'),
        };
    }
}
