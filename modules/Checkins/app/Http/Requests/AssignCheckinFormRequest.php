<?php

namespace Modules\Checkins\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCheckinFormRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clientIds' => ['required', 'array', 'min:1'],
            'clientIds.*' => ['integer', 'exists:clients,id'],
        ];
    }
}
