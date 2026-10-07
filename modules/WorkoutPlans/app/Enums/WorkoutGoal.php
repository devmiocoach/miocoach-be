<?php

namespace Modules\WorkoutPlans\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum WorkoutGoal: string implements HasLabel
{
    case Strength = 'strength';
    case WeightLoss = 'weight_loss';
    case MuscleGain = 'muscle_gain';
    case Endurance = 'endurance';
    case Flexibility = 'flexibility';
    case GeneralFitness = 'general_fitness';
    case Rehabilitation = 'rehabilitation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Strength => __('Strength'),
            self::WeightLoss => __('Weight Loss'),
            self::MuscleGain => __('Muscle Gain'),
            self::Endurance => __('Endurance'),
            self::Flexibility => __('Flexibility'),
            self::GeneralFitness => __('General Fitness'),
            self::Rehabilitation => __('Rehabilitation'),
        };
    }
}
