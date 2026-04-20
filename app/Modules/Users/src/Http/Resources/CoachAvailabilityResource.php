<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'day_of_week'      => $this->day_of_week,
            'start_time'       => $this->start_time,
            'end_time'         => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'is_recurring'     => $this->is_recurring,
        ];
    }
}
