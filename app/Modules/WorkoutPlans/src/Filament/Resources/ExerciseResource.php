<?php

namespace App\Modules\WorkoutPlans\Filament\Resources;

use App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource\Pages\CreateExercise;
use App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource\Pages\EditExercise;
use App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource\Pages\ListExercises;
use App\Modules\WorkoutPlans\Models\Exercise;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExerciseResource extends Resource
{
    protected static ?string $model = Exercise::class;
    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-bolt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Workout';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->nullable()->maxLength(255),
            Select::make('muscle_group')
                ->options([
                    'chest'     => 'Petto',
                    'back'      => 'Schiena',
                    'legs'      => 'Gambe',
                    'shoulders' => 'Spalle',
                    'arms'      => 'Braccia',
                    'core'      => 'Core',
                    'full_body' => 'Full Body',
                ])
                ->required(),
            Select::make('difficulty')
                ->options([
                    'beginner'     => 'Principiante',
                    'intermediate' => 'Intermedio',
                    'advanced'     => 'Avanzato',
                ])
                ->default('intermediate')
                ->required(),
            Select::make('equipment')
                ->options([
                    'barbell'    => 'Bilanciere',
                    'dumbbell'   => 'Manubri',
                    'bodyweight' => 'Corpo libero',
                    'machine'    => 'Macchina',
                    'cable'      => 'Cavi',
                    'kettlebell' => 'Kettlebell',
                ])
                ->nullable(),
            Textarea::make('description')->nullable()->rows(3),
            TextInput::make('video_url')->url()->nullable(),
            TextInput::make('thumbnail_url')->url()->nullable(),
            TagsInput::make('tags')->nullable(),
            Toggle::make('is_public')->label('Pubblico (visibile a tutti i coach)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('muscle_group')->badge()->sortable(),
                TextColumn::make('difficulty')->badge()->sortable(),
                TextColumn::make('equipment')->sortable(),
                IconColumn::make('is_public')->label('Pubblico')->boolean(),
                TextColumn::make('created_at')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('muscle_group')
                    ->options([
                        'chest' => 'Petto', 'back' => 'Schiena', 'legs' => 'Gambe',
                        'shoulders' => 'Spalle', 'arms' => 'Braccia', 'core' => 'Core', 'full_body' => 'Full Body',
                    ]),
                SelectFilter::make('difficulty')
                    ->options(['beginner' => 'Principiante', 'intermediate' => 'Intermedio', 'advanced' => 'Avanzato']),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListExercises::route('/'),
            'create' => CreateExercise::route('/create'),
            'edit'   => EditExercise::route('/{record}/edit'),
        ];
    }
}
