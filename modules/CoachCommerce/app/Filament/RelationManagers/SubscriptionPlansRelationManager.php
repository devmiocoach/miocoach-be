<?php

namespace Modules\CoachCommerce\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionPlans';

    protected static ?string $title = 'Abbonamenti';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Abbonamenti')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('price_monthly')
                    ->label('Mensile')
                    ->money('eur')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('price_yearly')
                    ->label('Annuale')
                    ->money('eur')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('sessions_per_month')
                    ->label('Sessioni/mese')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creato')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nessun abbonamento')
            ->emptyStateDescription('Questo coach non ha ancora creato abbonamenti.');
    }
}
