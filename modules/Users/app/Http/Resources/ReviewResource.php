<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'is_verified' => $this->is_verified,
            'reviewer_name' => $this->client?->user?->name ?? 'Anonimo',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
