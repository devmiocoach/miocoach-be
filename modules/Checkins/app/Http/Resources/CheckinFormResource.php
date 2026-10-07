<?php

namespace Modules\Checkins\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Checkins\Models\CheckinForm;

/** @mixin CheckinForm */
class CheckinFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'frequency' => $this->frequency,
            'sendTime' => substr((string) $this->send_time, 0, 5),
            'questions' => $this->questions,
            'isActive' => $this->is_active,
            'clientIds' => $this->whenLoaded('clients', fn () => $this->clients->pluck('id')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
