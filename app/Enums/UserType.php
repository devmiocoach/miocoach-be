<?php

namespace App\Enums;

enum UserType: string
{
    case ATLETA = 'atleta';
    case COACH = 'coach';

    public function label(): string
    {
        return match ($this) {
            self::ATLETA => __('Atleta'),
            self::COACH => __('Coach'),
        };
    }
}
