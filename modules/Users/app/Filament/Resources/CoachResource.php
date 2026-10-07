<?php

namespace Modules\Users\Filament\Resources;

use App\Modules\Users\Models\Coach;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\CoachCommerce\Filament\RelationManagers\SubscriptionPlansRelationManager;
use Modules\Users\Filament\Resources\CoachResource\Pages\EditCoach;
use Modules\Users\Filament\Resources\CoachResource\Pages\ListCoaches;
use Modules\Users\Filament\Resources\CoachResource\Pages\ViewCoach;
use Modules\Users\Filament\Resources\CoachResource\RelationManagers\PackagesRelationManager;

class CoachResource extends Resource
{
    protected static ?string $model = Coach::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestione Coach';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['display_name', 'user.name', 'user.email'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('mode')
                    ->label('Modalità')
                    ->badge(),
                IconColumn::make('is_published')
                    ->label('Pubblicato')
                    ->boolean(),
                TextColumn::make('onboarding_completed_at')
                    ->label('Onboarding')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('certification_records_count')
                    ->label('Cert.')
                    ->counts('certificationRecords'),
                TextColumn::make('created_at')
                    ->label('Creato')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Pubblicato'),
                SelectFilter::make('mode')
                    ->label('Modalità')
                    ->options([
                        'online' => 'Online',
                        'in_person' => 'In presenza',
                        'hybrid' => 'Ibrido',
                    ]),
                Filter::make('da_verificare')
                    ->label('Da verificare')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'certificationRecords',
                        fn (Builder $q) => $q->whereNull('verified_at')->where('file_url', '!=', '')
                    )),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getRelations(): array
    {
        return array_values(array_filter([
            PackagesRelationManager::class,
            // CoachCommerce is an optional module — only surface its relation
            // manager when it is installed, keeping Users decoupled from it.
            SubscriptionPlansRelationManager::class,
        ], 'class_exists'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoaches::route('/'),
            'view' => ViewCoach::route('/{record}'),
            'edit' => EditCoach::route('/{record}/edit'),
        ];
    }
}
