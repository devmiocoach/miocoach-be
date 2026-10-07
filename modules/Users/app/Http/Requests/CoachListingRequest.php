<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoachListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'array', 'max:3'],
            'mode.*' => ['string', 'in:online,in_person,hybrid'],
            'price_min' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'price_max' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'specialization' => ['nullable', 'array', 'max:10'],
            'specialization.*' => ['string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'rating_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'available_days' => ['nullable', 'array', 'max:7'],
            'available_days.*' => ['integer', 'between:0,6'],
            'time_slots' => ['nullable', 'array', 'max:3'],
            'time_slots.*' => ['string', 'in:mattina,pomeriggio,sera'],
            'sort' => ['nullable', 'in:relevance,rating_desc,price_asc,price_desc'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
