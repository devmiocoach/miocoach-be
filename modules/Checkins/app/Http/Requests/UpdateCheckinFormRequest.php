<?php

namespace Modules\Checkins\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Checkins\Enums\CheckinFrequency;
use Modules\Checkins\Enums\QuestionType;

class UpdateCheckinFormRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::enum(CheckinFrequency::class)],
            'sendTime' => ['required', 'date_format:H:i'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string', 'max:64'],
            'questions.*.text' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.order' => ['required', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
