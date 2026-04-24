<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientDetailResource extends JsonResource
{
    public function __construct($resource, private readonly ?string $decryptedAnamnesis = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $nameParts = explode(' ', $this->user?->name ?? '', 2);

        return [
            'id'         => $this->id,
            'first_name' => $nameParts[0] ?? null,
            'last_name'  => $nameParts[1] ?? null,
            'email'      => $this->user?->email,
            'phone'      => $this->phone,
            'avatar_url' => $this->avatar_url,
            'tags'       => $this->tags ?? [],
            'status'     => $this->status,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender'     => $this->gender,
            'height_cm'  => $this->height_cm,
            'weight_kg'  => $this->weight_kg,
            'goals'      => $this->goals,
            'joined_at'  => $this->joined_at?->toISOString(),
            'subscription_expires_at'         => $this->subscription_expires_at?->toISOString(),
            'subscription_sessions_remaining' => $this->subscription_sessions_remaining,
            'anamnesis' => $this->decryptedAnamnesis,
            'notes' => ClientNoteResource::collection(
                $this->whenLoaded('notes')
            ),
            'files' => ClientFileResource::collection(
                $this->whenLoaded('files')
            ),
            'sessions'        => [],
            'next_session'    => null,
            'payment_history' => [],
            'next_booking'    => null,
        ];
    }
}
