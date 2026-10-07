<?php

namespace Modules\Users\Filament\Resources;

use App\Modules\Users\Models\Certification;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Users\Filament\Resources\CertificationResource\Pages\ListCertifications;
use Modules\Users\Filament\Resources\CertificationResource\Pages\ViewCertification;

class CertificationResource extends Resource
{
    protected static ?string $model = Certification::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestione Coach';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Certification::query()
            ->whereNull('verified_at')
            ->where('file_url', '!=', '')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('coach.user.name')
                    ->label('Coach')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Certificazione')
                    ->searchable(),
                TextColumn::make('issuer')
                    ->label('Ente')
                    ->searchable(),
                TextColumn::make('issued_at')
                    ->label('Data rilascio')
                    ->date('d/m/Y'),
                IconColumn::make('file_url')
                    ->label('File')
                    ->icon(fn (?string $state): string => filled($state) ? 'heroicon-o-document' : 'heroicon-o-x-mark')
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray'),
                IconColumn::make('verified_at')
                    ->label('Verificato')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creato')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('verified_at')
                    ->label('Stato verifica')
                    ->nullable()
                    ->placeholder('Tutti')
                    ->trueLabel('Verificati')
                    ->falseLabel('In attesa'),
                TernaryFilter::make('has_file')
                    ->label('Ha file')
                    ->placeholder('Tutti')
                    ->trueLabel('Con file')
                    ->falseLabel('Senza file')
                    ->queries(
                        true: fn (Builder $query) => $query->where('file_url', '!=', ''),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('file_url')->orWhere('file_url', '')),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Action::make('verifica')
                    ->label('Verifica')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Certification $record): void {
                        $record->update(['verified_at' => now()]);
                    })
                    ->successNotificationTitle('Certificazione verificata')
                    ->visible(fn (Certification $record): bool => is_null($record->getAttribute('verified_at'))),
                Action::make('rifiuta')
                    ->label('Rifiuta')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo del rifiuto')
                            ->rows(3),
                    ])
                    ->action(function (Certification $record): void {
                        $record->delete();
                    })
                    ->successNotificationTitle('Certificazione rifiutata')
                    ->visible(fn (Certification $record): bool => is_null($record->getAttribute('verified_at'))),
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_verify')
                        ->label('Verifica selezionati')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function ($record): void {
                                $record->update(['verified_at' => now()]);
                            });
                        })
                        ->successNotificationTitle('Certificazioni verificate')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dettagli certificazione')
                ->schema([
                    TextEntry::make('coach.user.name')
                        ->label('Coach'),
                    TextEntry::make('name')
                        ->label('Nome'),
                    TextEntry::make('issuer')
                        ->label('Ente'),
                    TextEntry::make('issued_at')
                        ->label('Data rilascio')
                        ->date('d/m/Y'),
                    TextEntry::make('file_url')
                        ->label('Documento')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->visible(fn (?string $state): bool => filled($state)),
                    IconEntry::make('verified_at')
                        ->label('Verificato')
                        ->boolean(),
                ])
                ->columns(2),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertifications::route('/'),
            'view' => ViewCertification::route('/{record}'),
        ];
    }
}
