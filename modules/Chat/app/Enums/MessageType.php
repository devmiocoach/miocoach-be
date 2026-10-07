<?php

namespace Modules\Chat\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MessageType: string
{
    case Text = 'text';
    case File = 'file';
    case Image = 'image';
    case WorkoutPlan = 'workout_plan';
}
