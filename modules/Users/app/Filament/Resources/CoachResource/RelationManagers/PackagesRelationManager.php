<?php

namespace Modules\Users\Filament\Resources\CoachResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $title = 'Pacchetti';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pacchetti')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('sessions_count')
                    ->label('Sessioni')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_in_euros')
                    ->label('Prezzo')
                    ->money('eur')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('price_cents', $direction)),
                TextColumn::make('validity_days')
                    ->label('Validità')
                    ->suffix(' giorni')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('Nessun pacchetto')
            ->emptyStateDescription('Questo coach non ha ancora creato pacchetti.');
    }
}
