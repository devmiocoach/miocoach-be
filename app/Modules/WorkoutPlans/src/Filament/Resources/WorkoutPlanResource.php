<?php

namespace App\Modules\WorkoutPlans\Filament\Resources;

use App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource\Pages\CreateWorkoutPlan;
use App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource\Pages\EditWorkoutPlan;
use App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource\Pages\ListWorkoutPlans;
use App\Modules\WorkoutPlans\Models\WorkoutPlan;
use Filament\Forms\Components\Select;
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

class WorkoutPlanResource extends Resource
{
    protected static ?string $model = WorkoutPlan::class;
    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Workout';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('description')->nullable()->rows(3),
            Select::make('goal')
                ->options([
                    'strength'     => 'Forza',
                    'hypertrophy'  => 'Ipertrofia',
                    'weight_loss'  => 'Dimagrimento',
                    'endurance'    => 'Resistenza',
                    'flexibility'  => 'Flessibilità',
                ])
                ->nullable(),
            Select::make('difficulty')
                ->options(['beginner' => 'Principiante', 'intermediate' => 'Intermedio', 'advanced' => 'Avanzato'])
                ->default('intermediate'),
            Select::make('status')
                ->options(['draft' => 'Bozza', 'active' => 'Attivo', 'archived' => 'Archiviato'])
                ->default('draft')
                ->required(),
            TextInput::make('duration_weeks')->numeric()->nullable()->minValue(1)->maxValue(52),
            TextInput::make('sessions_per_week')->numeric()->default(3)->minValue(1)->maxValue(7),
            Toggle::make('is_template')->label('Usa come template'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('coach.name')->label('Coach')->sortable(),
                TextColumn::make('client.name')->label('Cliente')->sortable()->default('—'),
                TextColumn::make('goal')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'draft'    => 'warning',
                        'archived' => 'gray',
                        default    => 'gray',
                    }),
                IconColumn::make('is_template')->label('Template')->boolean(),
                TextColumn::make('exercises_count')->label('Esercizi')->counts('exercises'),
                TextColumn::make('created_at')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['draft' => 'Bozza', 'active' => 'Attivo', 'archived' => 'Archiviato']),
                SelectFilter::make('goal')
                    ->options([
                        'strength' => 'Forza', 'hypertrophy' => 'Ipertrofia',
                        'weight_loss' => 'Dimagrimento', 'endurance' => 'Resistenza', 'flexibility' => 'Flessibilità',
                    ]),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListWorkoutPlans::route('/'),
            'create' => CreateWorkoutPlan::route('/create'),
            'edit'   => EditWorkoutPlan::route('/{record}/edit'),
        ];
    }
}
