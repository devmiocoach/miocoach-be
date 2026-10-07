<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Schemas;

use App\Modules\Users\Models\Coach;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;
use Modules\WorkoutPlans\Models\Exercise;

class WorkoutPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Plan Details'))
                    ->schema([
                        Select::make('coach_id')
                            ->label(__('Coach'))
                            ->options(fn () => Coach::with('user')->get()->pluck('user.name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('goal')
                            ->label(__('Goal'))
                            ->options(WorkoutGoal::class)
                            ->nullable(),

                        Select::make('difficulty')
                            ->label(__('Difficulty'))
                            ->options(ExerciseDifficulty::class)
                            ->nullable(),

                        TextInput::make('duration_weeks')
                            ->label(__('Duration (weeks)'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(52)
                            ->nullable(),

                        TextInput::make('sessions_per_week')
                            ->label(__('Sessions per week'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(7)
                            ->nullable(),

                        Textarea::make('description')
                            ->label(__('Description'))
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('Settings'))
                    ->schema([
                        Toggle::make('is_template')
                            ->label(__('Reusable Template'))
                            ->helperText(__('Templates can be assigned to multiple clients'))
                            ->onColor('info')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->onColor('success')
                            ->default(true),
                    ]),

                Section::make(__('Days & Exercises'))
                    ->schema([
                        Repeater::make('workoutDays')
                            ->relationship()
                            ->label(__('Training Days'))
                            ->schema([
                                TextInput::make('day_number')
                                    ->label(__('Day Number'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(),

                                TextInput::make('name')
                                    ->label(__('Day Name'))
                                    ->placeholder('e.g. Push Day, Upper Body...')
                                    ->nullable(),

                                TextInput::make('order_index')
                                    ->label(__('Order'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->hidden(),

                                Repeater::make('workoutExercises')
                                    ->relationship()
                                    ->label(__('Exercises'))
                                    ->schema([
                                        Select::make('exercise_id')
                                            ->label(__('Exercise'))
                                            ->options(fn () => Exercise::active()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->columnSpanFull(),

                                        TextInput::make('sets')
                                            ->label(__('Sets'))
                                            ->numeric()->minValue(1)->nullable(),

                                        TextInput::make('reps_target')
                                            ->label(__('Reps Target'))
                                            ->numeric()->minValue(1)->nullable(),

                                        TextInput::make('weight_kg')
                                            ->label(__('Weight (kg)'))
                                            ->numeric()->minValue(0)->nullable(),

                                        TextInput::make('rir_target')
                                            ->label(__('RIR Target'))
                                            ->numeric()->minValue(0)->maxValue(10)->nullable(),

                                        TextInput::make('rest_seconds')
                                            ->label(__('Rest (sec)'))
                                            ->numeric()->minValue(0)->nullable(),

                                        TextInput::make('order_index')
                                            ->label(__('Order'))
                                            ->numeric()->minValue(0)->default(0)->hidden(),

                                        Textarea::make('notes')
                                            ->label(__('Notes'))
                                            ->nullable()->rows(2)->columnSpanFull(),
                                    ])
                                    ->addActionLabel(__('Add Exercise'))
                                    ->defaultItems(0)
                                    ->orderColumn('order_index')
                                    ->columns(4)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => isset($state['exercise_id'])
                                        ? Exercise::find($state['exercise_id'])?->name
                                        : null
                                    )
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel(__('Add Day'))
                            ->defaultItems(0)
                            ->orderColumn('order_index')
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => isset($state['name']) && $state['name']
                                ? "Day {$state['day_number']}: {$state['name']}"
                                : "Day {$state['day_number']}"
                            )
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
