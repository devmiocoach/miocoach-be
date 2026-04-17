<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'avatar'            => $this->avatar,
            'locale'            => $this->locale,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'role'              => $this->getRoleNames()->first(),
            'two_factor_enabled'=> $this->hasEnabledTwoFactorAuthentication(),
            'coach'             => $this->whenLoaded('coach', fn() => new CoachResource($this->coach)),
            'client'            => $this->whenLoaded('client', fn() => new ClientResource($this->client)),
            'created_at'        => $this->created_at->toISOString(),
        ];
    }
}
