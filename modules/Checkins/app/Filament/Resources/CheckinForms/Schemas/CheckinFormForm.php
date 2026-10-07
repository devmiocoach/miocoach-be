<?php

namespace Modules\Checkins\Filament\Resources\CheckinForms\Schemas;

use App\Modules\Users\Models\Coach;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Checkins\Enums\CheckinFrequency;
use Modules\Checkins\Enums\QuestionType;

class CheckinFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Details'))
                ->columnSpanFull()
                ->schema([
                    Select::make('coach_id')
                        ->label(__('Coach'))
                        ->options(fn () => Coach::with('user')->get()->pluck('user.name', 'id'))
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('title')
                        ->label(__('Title'))
                        ->required()
                        ->maxLength(255),

                    Select::make('frequency')
                        ->label(__('Frequency'))
                        ->options(CheckinFrequency::class)
                        ->required(),

                    TimePicker::make('send_time')
                        ->label(__('Send time'))
                        ->seconds(false)
                        ->required(),

                    Toggle::make('is_active')
                        ->label(__('Active'))
                        ->default(true),
                ])
                ->columns(2),

            Repeater::make('questions')
                ->label(__('Questions'))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('id')
                        ->label(__('Question ID'))
                        ->required()
                        ->maxLength(64),

                    TextInput::make('text')
                        ->label(__('Text'))
                        ->required()
                        ->maxLength(500),

                    Select::make('type')
                        ->label(__('Type'))
                        ->options(QuestionType::class)
                        ->required(),

                    Toggle::make('required')
                        ->label(__('Required'))
                        ->default(false),

                    TextInput::make('order')
                        ->label(__('Order'))
                        ->numeric()
                        ->default(0),
                ])
                ->orderColumn('order')
                ->minItems(1)
                ->addActionLabel(__('Add Question')),
        ]);
    }
}
