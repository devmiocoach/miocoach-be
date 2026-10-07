<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkoutSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('workoutPlan.name')
                    ->label(__('Plan'))
                    ->default('—')
                    ->sortable(),

                TextColumn::make('workoutDay.name')
                    ->label(__('Day'))
                    ->default('—'),

                TextColumn::make('started_at')
                    ->label(__('Started'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label(__('Completed'))
                    ->dateTime()
                    ->default('—')
                    ->sortable(),

                TextColumn::make('total_volume_kg')
                    ->label(__('Volume (kg)'))
                    ->numeric()
                    ->default('—')
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label(__('Duration (min)'))
                    ->default('—')
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
