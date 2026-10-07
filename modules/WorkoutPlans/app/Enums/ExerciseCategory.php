<?php

namespace Modules\WorkoutPlans\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExerciseCategory: string implements HasLabel
{
    case Strength = 'strength';
    case Cardio = 'cardio';
    case Flexibility = 'flexibility';
    case Mobility = 'mobility';
    case Balance = 'balance';
    case Hiit = 'hiit';
    case Stretching = 'stretching';
    case Warmup = 'warmup';
    case Cooldown = 'cooldown';
    case Sport = 'sport';

    public function getLabel(): string
    {
        return match ($this) {
            self::Strength => __('Strength'),
            self::Cardio => __('Cardio'),
            self::Flexibility => __('Flexibility'),
            self::Mobility => __('Mobility'),
            self::Balance => __('Balance'),
            self::Hiit => __('HIIT'),
            self::Stretching => __('Stretching'),
            self::Warmup => __('Warm-up'),
            self::Cooldown => __('Cool-down'),
            self::Sport => __('Sport-specific'),
        };
    }
}
