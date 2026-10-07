<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasLabel
{
    /**
     * Define role cases
     *
     * @case ADMIN - Administrator with full access to Filament panel and dashboard
     * @case USER - Regular user with limited access to dashboard
     * @case SUBSCRIBER - Additive role for users with an active subscription
     * @case COACH - Coach (SaaS B2B customer) — can access coaching management tools and must have an active subscription
     * @case CLIENT - Athlete/Client (B2C end-user) — can access the client portal for free
     */
    case ADMIN = 'admin';
    case USER = 'user';
    case SUBSCRIBER = 'subscriber';
    case COACH = 'coach';
    case CLIENT = 'client';

    /**
     * Get the human-readable label for the role
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => __('Administrator'),
            self::USER => __('User'),
            self::SUBSCRIBER => __('Subscriber'),
            self::COACH => __('Coach'),
            self::CLIENT => __('Client'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ADMIN => 'danger',
            self::USER => 'info',
            self::SUBSCRIBER => 'success',
            self::COACH => 'warning',
            self::CLIENT => 'primary',
        };
    }

    /**
     * Get all role values as array
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get all role labels as array
     */
    public static function labels(): array
    {
        return array_map(fn ($case) => $case->getLabel(), self::cases());
    }

    /**
     * Get role from string with default fallback
     */
    public static function fromString(?string $roleName): self
    {
        return self::tryFrom($roleName) ?? self::USER;
    }
}
