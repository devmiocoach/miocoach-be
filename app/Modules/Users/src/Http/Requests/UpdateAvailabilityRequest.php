<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots'                    => ['required', 'array'],
            'slots.*.day_of_week'      => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'       => ['required', 'date_format:H:i'],
            'slots.*.end_time'         => ['required', 'date_format:H:i'],
            'slots.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'slots.*.is_recurring'     => ['required', 'boolean'],
        ];
    }
}
