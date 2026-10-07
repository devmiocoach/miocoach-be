<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutSessions;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Pages\ListWorkoutSessions;
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Tables\WorkoutSessionsTable;
use Modules\WorkoutPlans\Models\WorkoutSession;

class WorkoutSessionResource extends Resource
{
    protected static ?string $model = WorkoutSession::class;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('Workout Plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sessions');
    }

    public static function table(Table $table): Table
    {
        return WorkoutSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkoutSessions::route('/'),
        ];
    }
}
