<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OnboardingStep8Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'no_show_fee_cents' => ['nullable', 'integer', 'min:0'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'in:10,15,20,30'],
            'free_first_session' => ['required', 'boolean'],
            'welcome_discount_enabled' => ['required', 'boolean'],
            'welcome_discount_percent' => ['nullable', 'required_if:welcome_discount_enabled,true', 'integer', 'min:1', 'max:100'],
            'custom_terms' => ['nullable', 'string', 'max:2000'],
            // `sometimes` keeps older mobile builds that don't send these fields working.
            'accepts_free_consultation' => ['sometimes', 'boolean'],
            'accepts_direct_message' => ['sometimes', 'boolean'],
            'accepts_direct_booking' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $coach = $this->user()?->coach;
                $channels = ['accepts_free_consultation', 'accepts_direct_message', 'accepts_direct_booking'];
                $anyEnabled = collect($channels)->contains(fn ($field) => $this->has($field)
                    ? $this->boolean($field)
                    : (bool) ($coach->{$field} ?? $field === 'accepts_direct_booking'));

                if (! $anyEnabled) {
                    $validator->errors()->add('contact_channels', 'Scegli almeno un modo in cui i clienti possono contattarti.');
                }
            },
        ];
    }
}
