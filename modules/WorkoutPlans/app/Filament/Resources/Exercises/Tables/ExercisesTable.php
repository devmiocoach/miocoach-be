<?php

namespace Modules\WorkoutPlans\Filament\Resources\Exercises\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;

class ExercisesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label(__('Category'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('difficulty')
                    ->label(__('Difficulty'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->default(__('Global'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('muscle_groups')
                    ->label(__('Muscle Groups'))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label(__('Active'))
                    ->onColor('success'),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('Category'))
                    ->options(ExerciseCategory::class),

                SelectFilter::make('difficulty')
                    ->label(__('Difficulty'))
                    ->options(ExerciseDifficulty::class),

                TernaryFilter::make('is_active')
                    ->label(__('Active Only')),

                TernaryFilter::make('coach_id')
                    ->label(__('Global Only'))
                    ->nullable(),

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
            ->defaultSort('name');
    }
}
