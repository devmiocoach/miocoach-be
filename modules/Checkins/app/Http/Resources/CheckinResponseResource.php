<?php

namespace Modules\Checkins\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Checkins\Models\CheckinResponse;

/** @mixin CheckinResponse */
class CheckinResponseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $questions = collect($this->whenLoaded('checkinForm', fn () => $this->checkinForm->questions, []));
        $questionsById = $questions->keyBy('id');

        return [
            'id' => $this->id,
            'checkinFormId' => $this->checkin_form_id,
            'clientId' => $this->client_id,
            'submittedAt' => $this->submitted_at->toIso8601String(),
            'answers' => collect($this->answers)->map(fn ($a) => [
                'questionId' => $a['questionId'],
                'questionText' => $questionsById->get($a['questionId'])['text'] ?? null,
                'type' => $questionsById->get($a['questionId'])['type'] ?? null,
                'value' => $a['value'],
            ])->values(),
        ];
    }
}
