<?php

namespace App\Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function __construct(private readonly array $data)
    {
        parent::__construct($data);
    }

    public function toArray(Request $request): array
    {
        return array_filter([
            'access_token' => $this->data['access_token'] ?? null,
            'token_type'   => $this->data['token_type'] ?? null,
            'expires_in'   => $this->data['expires_in'] ?? null,
            'user'         => $this->data['user'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
