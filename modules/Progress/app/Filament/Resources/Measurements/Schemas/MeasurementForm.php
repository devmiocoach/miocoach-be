<?php

namespace Modules\Progress\Filament\Resources\Measurements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Progress\Enums\MetricType;

class MeasurementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Measurement'))
                    ->schema([
                        Select::make('coach_id')
                            ->label(__('Coach'))
                            ->relationship('coach', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name ?? "Coach #{$record->id}")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('client_id')
                            ->label(__('Client'))
                            ->relationship('client', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name ?? "Client #{$record->id}")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('metric_type')
                            ->label(__('Metric'))
                            ->options(MetricType::class)
                            ->required(),

                        TextInput::make('value')
                            ->label(__('Value'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(999999.99),

                        DateTimePicker::make('measured_at')
                            ->label(__('Measured At'))
                            ->required()
                            ->maxDate(now()),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->nullable()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
