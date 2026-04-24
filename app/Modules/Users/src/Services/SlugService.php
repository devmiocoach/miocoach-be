<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\Coach;
use Illuminate\Support\Str;

class SlugService
{
    public function generate(string $displayName, string $city, ?int $excludeCoachId = null): string
    {
        $base = Str::slug($displayName . '-' . $city);
        $slug = $base;
        $i    = 1;

        while ($this->slugExists($slug, $excludeCoachId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeCoachId): bool
    {
        return Coach::where('slug', $slug)
            ->when($excludeCoachId, fn ($q) => $q->where('id', '!=', $excludeCoachId))
            ->exists();
    }
}
