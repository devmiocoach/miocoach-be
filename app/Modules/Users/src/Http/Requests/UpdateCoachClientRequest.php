<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'tags'       => ['sometimes', 'nullable', 'array'],
            'tags.*'     => ['string', 'max:50'],
            'status'     => ['sometimes', 'in:active,inactive,suspended'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'     => ['sometimes', 'nullable', 'in:male,female,other'],
            'height_cm'  => ['sometimes', 'nullable', 'numeric', 'min:50', 'max:300'],
            'weight_kg'  => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
            'goals'      => ['sometimes', 'nullable', 'array'],
            'goals.*'    => ['string', 'max:255'],
        ];
    }
}
