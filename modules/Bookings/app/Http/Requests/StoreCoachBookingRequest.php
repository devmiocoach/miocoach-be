<?php

namespace Modules\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Bookings\Enums\BookingServiceType;

class StoreCoachBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'service_type' => ['required', 'string', 'in:'.implode(',', array_column(BookingServiceType::cases(), 'value'))],
            'package_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
