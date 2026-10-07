<?php

namespace Modules\Progress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Progress\Enums\MetricType;

class UpdateMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'metric_type' => ['sometimes', 'string', Rule::in(MetricType::values())],
            'value' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'measured_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
