<?php

namespace Modules\Users\Filament\Resources\CertificationResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Modules\Users\Filament\Resources\CertificationResource;

class ViewCertification extends ViewRecord
{
    protected static string $resource = CertificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifica')
                ->label('Verifica')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->getRecord()->update(['verified_at' => now()]);
                })
                ->successNotificationTitle('Certificazione verificata')
                ->visible(fn (): bool => is_null($this->getRecord()->getAttribute('verified_at'))),
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
                ->action(function (): void {
                    $this->getRecord()->delete();
                    $this->redirect(CertificationResource::getUrl('index'));
                })
                ->successNotificationTitle('Certificazione rifiutata')
                ->visible(fn (): bool => is_null($this->getRecord()->getAttribute('verified_at'))),
        ];
    }
}
