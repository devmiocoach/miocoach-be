<?php

namespace Modules\WorkoutPlans\Filament\Resources\Exercises\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;

class ExerciseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Basic Information'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('category')
                            ->label(__('Category'))
                            ->options(ExerciseCategory::class)
                            ->required(),

                        Select::make('difficulty')
                            ->label(__('Difficulty'))
                            ->options(ExerciseDifficulty::class)
                            ->required(),

                        Textarea::make('description')
                            ->label(__('Description'))
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('instructions')
                            ->label(__('Instructions'))
                            ->nullable()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('Targeting'))
                    ->schema([
                        TagsInput::make('muscle_groups')
                            ->label(__('Muscle Groups'))
                            ->suggestions(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core', 'calves', 'forearms', 'hamstrings', 'quads'])
                            ->nullable()
                            ->columnSpanFull(),

                        TagsInput::make('primary_muscles')
                            ->label(__('Primary Muscles'))
                            ->suggestions(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core', 'calves', 'forearms', 'hamstrings', 'quads'])
                            ->nullable()
                            ->columnSpanFull(),

                        TagsInput::make('secondary_muscles')
                            ->label(__('Secondary Muscles'))
                            ->suggestions(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core', 'calves', 'forearms', 'hamstrings', 'quads'])
                            ->nullable()
                            ->columnSpanFull(),

                        TagsInput::make('equipment')
                            ->label(__('Equipment'))
                            ->suggestions(['barbell', 'dumbbells', 'kettlebell', 'resistance_band', 'machine', 'cable', 'bodyweight', 'pull_up_bar', 'bench', 'trx'])
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Media'))
                    ->schema([
                        TextInput::make('video_url')
                            ->label(__('Video URL'))
                            ->url()
                            ->nullable()
                            ->columnSpanFull(),

                        TextInput::make('thumbnail_url')
                            ->label(__('Thumbnail URL'))
                            ->url()
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Status'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->onColor('success')
                            ->default(true),
                    ]),
            ]);
    }
}
