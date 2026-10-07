<?php

namespace Modules\Progress\Filament\Resources\Measurements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Progress\Enums\MetricType;

class MeasurementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('metric_type')
                    ->label(__('Metric'))
                    ->badge(),

                TextColumn::make('value')
                    ->label(__('Value'))
                    ->formatStateUsing(fn ($record) => $record->value.' '.$record->metric_type->getUnit())
                    ->sortable(),

                TextColumn::make('measured_at')
                    ->label(__('Date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('metric_type')
                    ->label(__('Metric'))
                    ->options(MetricType::class),
            ])
            ->defaultSort('measured_at', 'desc');
    }
}
