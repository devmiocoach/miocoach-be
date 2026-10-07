<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;

class WorkoutPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('goal')
                    ->label(__('Goal'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('difficulty')
                    ->label(__('Difficulty'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->default('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('duration_weeks')
                    ->label(__('Weeks'))
                    ->suffix(' wk')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sessions_per_week')
                    ->label(__('Sessions/wk'))
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_template')
                    ->label(__('Template'))
                    ->boolean()
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label(__('Active'))
                    ->onColor('success'),

                TextColumn::make('workoutExercises_count')
                    ->label(__('Exercises'))
                    ->counts('workoutExercises')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('goal')
                    ->label(__('Goal'))
                    ->options(WorkoutGoal::class),

                SelectFilter::make('difficulty')
                    ->label(__('Difficulty'))
                    ->options(ExerciseDifficulty::class),

                TernaryFilter::make('is_template')
                    ->label(__('Templates Only')),

                TernaryFilter::make('is_active')
                    ->label(__('Active Only')),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
