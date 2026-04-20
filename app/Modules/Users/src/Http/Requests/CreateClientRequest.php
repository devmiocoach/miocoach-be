<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'      => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:30'],
            'tags'       => ['sometimes', 'nullable', 'array'],
            'tags.*'     => ['string', 'max:50'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'     => ['sometimes', 'nullable', 'in:male,female,other'],
            'height_cm'  => ['sometimes', 'nullable', 'numeric', 'min:50', 'max:300'],
            'weight_kg'  => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
        ];
    }
}
