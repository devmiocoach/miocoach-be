<?php

namespace Modules\Checkins\Filament\Resources\CheckinResponses\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CheckinResponseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Response'))
                ->schema([
                    TextEntry::make('checkinForm.title')
                        ->label(__('Form')),

                    TextEntry::make('client.user.name')
                        ->label(__('Client')),

                    TextEntry::make('submitted_at')
                        ->label(__('Submitted at'))
                        ->dateTime(),
                ])
                ->columns(3),

            Section::make(__('Answers'))
                ->schema([
                    RepeatableEntry::make('answers')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('questionId')
                                ->label(__('Question')),

                            TextEntry::make('value')
                                ->label(__('Answer')),
                        ])
                        ->columns(2),
                ]),
        ]);
    }
}
