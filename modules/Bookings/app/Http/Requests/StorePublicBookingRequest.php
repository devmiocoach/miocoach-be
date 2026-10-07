<?php

namespace Modules\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Bookings\Enums\BookingServiceType;

class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coach_id' => ['required', 'integer', 'exists:coaches,id'],
            'slot_starts_at' => ['required', 'date', 'after:now'],
            'service_type' => ['required', 'string', 'in:'.implode(',', array_column(BookingServiceType::cases(), 'value'))],
            'package_id' => ['nullable', 'integer'],
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
        ];
    }
}
