<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single entry of the coach activity feed.
 *
 * `metadata` is a free-form JSON bag whose keys depend on `event_type`
 * (e.g. `client_name` for `new_booking`) — clients must treat it as optional.
 */
class CoachActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
