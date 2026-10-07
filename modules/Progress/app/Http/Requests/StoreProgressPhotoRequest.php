<?php

namespace Modules\Progress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Progress\Enums\PhotoPose;

class StoreProgressPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:10240'],
            'pose' => ['nullable', 'string', Rule::in(array_column(PhotoPose::cases(), 'value'))],
            'taken_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
