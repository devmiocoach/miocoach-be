<?php

namespace Modules\Progress\Filament\Resources\ProgressPhotos\Tables;

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Progress\Enums\PhotoPose;

class ProgressPhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('storage_key')
                    ->label(__('Photo'))
                    ->disk('private')
                    ->size(60),

                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pose')
                    ->label(__('Pose'))
                    ->badge(),

                TextColumn::make('taken_at')
                    ->label(__('Taken At'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('pose')
                    ->label(__('Pose'))
                    ->options(PhotoPose::class),
            ])
            ->defaultSort('taken_at', 'desc');
    }
}
