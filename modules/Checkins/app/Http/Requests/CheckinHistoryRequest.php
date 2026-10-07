<?php

namespace Modules\Checkins\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckinHistoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
