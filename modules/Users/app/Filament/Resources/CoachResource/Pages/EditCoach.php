<?php

namespace Modules\Users\Filament\Resources\CoachResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Users\Filament\Resources\CoachResource;

class EditCoach extends EditRecord
{
    protected static string $resource = CoachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profilo')
                ->schema([
                    TextInput::make('display_name')
                        ->label('Display Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('tagline')
                        ->label('Tagline')
                        ->maxLength(255),
                    Textarea::make('bio')
                        ->label('Bio')
                        ->rows(4)
                        ->columnSpanFull(),
                    Select::make('mode')
                        ->label('Modalità')
                        ->options([
                            'online' => 'Online',
                            'in_person' => 'In presenza',
                            'hybrid' => 'Ibrido',
                        ]),
                    Toggle::make('is_published')
                        ->label('Pubblicato'),
                ])
                ->columns(2),

            Section::make('Certificazioni')
                ->schema([
                    Repeater::make('certificationRecords')
                        ->label('')
                        ->relationship()
                        ->schema([
                            TextInput::make('name')
                                ->label('Nome')
                                ->required(),
                            TextInput::make('issuer')
                                ->label('Ente'),
                            DatePicker::make('verified_at')
                                ->label('Verificato il'),
                        ])
                        ->columns(3),
                ]),
        ]);
    }
}
