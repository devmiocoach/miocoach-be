<?php

namespace Modules\Checkins\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Checkins\Models\CheckinForm;

class RespondCheckinRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.questionId' => ['required', 'string'],
            'answers.*.value' => ['present'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var CheckinForm $form */
            $form = $this->route('checkinForm');
            $answers = collect($this->input('answers', []))->keyBy('questionId');

            foreach ($form->questions as $question) {
                if ($question['required']) {
                    $value = $answers->get($question['id'])['value'] ?? null;
                    if ($value === null || $value === '') {
                        $validator->errors()->add('answers', "Risposta obbligatoria mancante: {$question['id']}.");
                    }
                }
            }
        });
    }
}
