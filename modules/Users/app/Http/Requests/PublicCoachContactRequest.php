<?php

namespace App\Modules\Users\Http\Requests;

use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublicCoachContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:message,free_consultation'],
            'message' => ['nullable', 'string', 'max:1000', 'required_if:type,message'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $coach = $this->route('coach');
                $options = $coach instanceof Coach ? $coach->contactOptions() : [];
                $allowed = match ($this->input('type')) {
                    'message' => $options['direct_message'] ?? false,
                    'free_consultation' => $options['free_consultation'] ?? false,
                    default => true,
                };
                if (! $allowed) {
                    $validator->errors()->add('type', 'Questo coach non accetta questo tipo di contatto.');
                }
            },
        ];
    }
}
