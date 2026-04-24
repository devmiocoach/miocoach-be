<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachContactRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'message'    => $this->message,
            'created_at' => $this->created_at?->toISOString(),
            'client'     => $this->whenLoaded('client', fn () => [
                'id'         => $this->client->id,
                'name'       => $this->client->user?->name,
                'email'      => $this->client->user?->email,
                'avatar_url' => $this->client->avatar_url,
            ]),
        ];
    }
}
