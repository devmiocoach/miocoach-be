<?php

namespace Modules\Progress\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PhotoPose: string implements HasLabel
{
    case Front = 'front';
    case Back = 'back';
    case SideLeft = 'side_left';
    case SideRight = 'side_right';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Front => 'Frontale',
            self::Back => 'Posteriore',
            self::SideLeft => 'Laterale sinistro',
            self::SideRight => 'Laterale destro',
            self::Custom => 'Altra posa',
        };
    }
}
