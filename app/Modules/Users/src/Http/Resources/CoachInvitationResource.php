<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'email'       => $this->email,
            'status'      => $this->status,
            'expires_at'  => $this->expires_at->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'created_at'  => $this->created_at->toISOString(),
        ];
    }
}
