<?php

namespace Modules\Checkins\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum QuestionType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case Rating = 'rating';
    case YesNo = 'yes_no';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Number => __('Number'),
            self::Rating => __('Rating'),
            self::YesNo => __('Yes / No'),
        };
    }
}
