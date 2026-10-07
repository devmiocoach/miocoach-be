<?php

namespace Modules\Progress\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MetricType: string implements HasColor, HasLabel
{
    case Weight = 'weight';
    case BodyFat = 'body_fat';
    case Waist = 'waist';
    case Chest = 'chest';
    case Hips = 'hips';
    case Arms = 'arms';
    case Thighs = 'thighs';
    case Shoulders = 'shoulders';
    case Neck = 'neck';
    case Calves = 'calves';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weight => 'Peso',
            self::BodyFat => 'Massa grassa',
            self::Waist => 'Vita',
            self::Chest => 'Petto',
            self::Hips => 'Fianchi',
            self::Arms => 'Braccia',
            self::Thighs => 'Gambe',
            self::Shoulders => 'Spalle',
            self::Neck => 'Collo',
            self::Calves => 'Polpacci',
        };
    }

    public function getUnit(): string
    {
        return match ($this) {
            self::Weight => 'kg',
            self::BodyFat => '%',
            default => 'cm',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Weight => 'primary',
            self::BodyFat => 'warning',
            self::Waist => 'success',
            self::Chest => 'info',
            self::Hips => 'danger',
            self::Arms => 'gray',
            self::Thighs => 'primary',
            self::Shoulders => 'info',
            self::Neck => 'warning',
            self::Calves => 'success',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
