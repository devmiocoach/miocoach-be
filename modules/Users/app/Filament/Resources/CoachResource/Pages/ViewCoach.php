<?php

namespace Modules\Users\Filament\Resources\CoachResource\Pages;

use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Users\Filament\Resources\CoachResource;

class ViewCoach extends ViewRecord
{
    protected static string $resource = CoachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profilo')
                ->schema([
                    ImageEntry::make('profile_photo_url')
                        ->label('Foto')
                        ->circular(),
                    TextEntry::make('display_name')
                        ->label('Display Name'),
                    TextEntry::make('tagline')
                        ->label('Tagline'),
                    TextEntry::make('bio')
                        ->label('Bio')
                        ->columnSpanFull(),
                    TextEntry::make('mode')
                        ->label('Modalità')
                        ->badge(),
                    TextEntry::make('languages')
                        ->label('Lingue')
                        ->badge(),
                    TextEntry::make('city')
                        ->label('Città'),
                    TextEntry::make('service_radius_km')
                        ->label('Raggio (km)'),
                ])
                ->columns(2),

            Section::make('Certificazioni')
                ->schema([
                    RepeatableEntry::make('certificationRecords')
                        ->label('')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Nome'),
                            TextEntry::make('issuer')
                                ->label('Ente'),
                            TextEntry::make('issued_at')
                                ->label('Data')
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
                        ->columns(5),
                ]),

            Section::make('Documenti')
                ->schema([
                    TextEntry::make('p_iva')
                        ->label('P. IVA'),
                    IconEntry::make('has_insurance')
                        ->label('Assicurazione')
                        ->boolean(),
                    TextEntry::make('insurance_company')
                        ->label('Compagnia'),
                    TextEntry::make('insurance_expiry')
                        ->label('Scadenza')
                        ->date('d/m/Y'),
                    TextEntry::make('insurance_file_url')
                        ->label('Polizza')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->visible(fn (?string $state): bool => filled($state)),
                ])
                ->columns(2),

            Section::make('Pricing')
                ->schema([
                    TextEntry::make('single_session_price_cents')
                        ->label('Sessione singola')
                        ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 100, 2, ',', '.').' EUR' : '-'),
                    TextEntry::make('monthly_subscription_cents')
                        ->label('Abbonamento mensile')
                        ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 100, 2, ',', '.').' EUR' : '-'),
                    TextEntry::make('quarterly_subscription_cents')
                        ->label('Abbonamento trimestrale')
                        ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 100, 2, ',', '.').' EUR' : '-'),
                ])
                ->columns(3),

            Section::make('Policy')
                ->schema([
                    TextEntry::make('no_show_fee_cents')
                        ->label('No-show fee')
                        ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 100, 2, ',', '.').' EUR' : '-'),
                    TextEntry::make('late_tolerance_minutes')
                        ->label('Tolleranza ritardo (min)'),
                    IconEntry::make('free_first_session')
                        ->label('Prima sessione gratuita')
                        ->boolean(),
                    IconEntry::make('welcome_discount_enabled')
                        ->label('Sconto benvenuto')
                        ->boolean(),
                    TextEntry::make('welcome_discount_percent')
                        ->label('Sconto %')
                        ->suffix('%'),
                    TextEntry::make('custom_terms')
                        ->label('Termini personalizzati')
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }
}
