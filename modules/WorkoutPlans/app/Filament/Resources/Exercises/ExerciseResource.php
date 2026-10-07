<?php

namespace Modules\WorkoutPlans\Filament\Resources\Exercises;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Pages\CreateExercise;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Pages\EditExercise;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Pages\ListExercises;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Schemas\ExerciseForm;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Tables\ExercisesTable;
use Modules\WorkoutPlans\Models\Exercise;

class ExerciseResource extends Resource
{
    protected static ?string $model = Exercise::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Workout Plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('Exercises');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }

    public static function form(Schema $schema): Schema
    {
        return ExerciseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExercisesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExercises::route('/'),
            'create' => CreateExercise::route('/create'),
            'edit' => EditExercise::route('/{record}/edit'),
        ];
    }
}
