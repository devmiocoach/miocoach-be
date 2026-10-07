<?php

namespace Modules\Bookings\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BookingServiceType: string implements HasLabel
{
    case PersonalTraining = 'personal_training';
    case OnlineCoaching = 'online_coaching';
    case Assessment = 'assessment';
    case GroupSession = 'group_session';
    case NutritionConsultation = 'nutrition_consultation';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PersonalTraining => 'Personal Training',
            self::OnlineCoaching => 'Online Coaching',
            self::Assessment => 'Assessment',
            self::GroupSession => 'Group Session',
            self::NutritionConsultation => 'Nutrition Consultation',
        };
    }
}
