<?php

namespace Modules\Bookings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coach_id' => $this->coach_id,
            'client_id' => $this->client_id,
            'package_id' => $this->package_id,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'service_type' => $this->service_type,
            'service_type_label' => $this->service_type?->getLabel(),
            // Present only when the caller eager-loads the relation. `whenLoaded`
            // yields null by itself for a guest booking (no client at all).
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->user?->name,
                'avatar' => $this->client->user?->profile_photo_url,
            ]),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'notes' => $this->notes,
            'price' => $this->price,
            'source' => $this->source,
            'cancellation_token' => $this->when($this->cancellation_token !== null, $this->cancellation_token),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
