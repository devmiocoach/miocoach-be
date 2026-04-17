<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rules\Password;

class Register extends BaseRegister
{
    protected function getEmailFormComponent(): TextInput
    {
        return TextInput::make('email')
            ->label("Indirizzo email")
           ->email()
            ->required()
            ->maxLength(255)
            ->unique('users', 'email')
            ->rules(['email:rfc,dns'])
            ->autocomplete('email')
            ->autofocus();
    }

    protected function getPasswordFormComponent(): TextInput
    {
        return TextInput::make('password')
            ->label("Password")
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rules([
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ])
            ->same('passwordConfirmation')
            ->validationAttribute('password');
    }
}
