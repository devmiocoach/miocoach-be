<?php

namespace Modules\Progress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_value' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'target_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
