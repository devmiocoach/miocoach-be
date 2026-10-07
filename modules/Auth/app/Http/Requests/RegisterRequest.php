<?php

namespace Modules\Auth\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', Rules\Password::defaults()],
            'password_confirmation' => 'required',
            'type' => ['required', 'string', 'in:atleta,coach'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->password !== $this->password_confirmation) {
                    $validator->errors()->add(
                        'password_confirmation',
                        'Le password non coincidono.'
                    );
                }
            },
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'password' => Hash::make($this->input('password')),
        ]);
    }
}
