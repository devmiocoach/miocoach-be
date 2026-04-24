<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'issuer'      => $this->issuer,
            'issued_at'   => $this->issued_at?->toDateString(),
            'file_url'    => $this->file_url,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
