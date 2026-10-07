<?php

namespace Modules\Progress\Filament\Resources\ProgressPhotos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Progress\Enums\PhotoPose;

class ProgressPhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Photo'))
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

                        FileUpload::make('storage_key')
                            ->label(__('Photo'))
                            ->image()
                            ->disk('private')
                            ->directory('progress-photos')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('pose')
                            ->label(__('Pose'))
                            ->options(PhotoPose::class)
                            ->nullable(),

                        DatePicker::make('taken_at')
                            ->label(__('Taken At'))
                            ->required()
                            ->maxDate(now()),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->nullable()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
