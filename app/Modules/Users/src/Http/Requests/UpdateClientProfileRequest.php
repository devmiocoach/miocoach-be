<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anamnesi'   => ['sometimes', 'nullable', 'string', 'max:10000'],
            'goals'      => ['sometimes', 'nullable', 'array'],
            'goals.*'    => ['string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender'     => ['sometimes', 'nullable', 'in:male,female,other'],
            'height_cm'  => ['sometimes', 'nullable', 'numeric', 'min:50', 'max:300'],
            'weight_kg'  => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
        ];
    }
}
