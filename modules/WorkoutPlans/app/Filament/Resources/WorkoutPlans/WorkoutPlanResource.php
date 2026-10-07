<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutPlans;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages\CreateWorkoutPlan;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages\EditWorkoutPlan;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages\ListWorkoutPlans;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Schemas\WorkoutPlanForm;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Tables\WorkoutPlansTable;
use Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanResource extends Resource
{
    protected static ?string $model = WorkoutPlan::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Workout Plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('Workout Plans');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkoutPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkoutPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkoutPlans::route('/'),
            'create' => CreateWorkoutPlan::route('/create'),
            'edit' => EditWorkoutPlan::route('/{record}/edit'),
        ];
    }
}
