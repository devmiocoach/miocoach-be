<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\SlugService;

class UpdateCoachProfileAction
{
    public function __construct(
        private readonly GeocodingService $geocoding,
        private readonly SlugService $slug,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Coach $coach, array $data): Coach
    {
        // Update user.name if display_name is provided
        if (isset($data['display_name'])) {
            $coach->user->update(['name' => $data['display_name']]);
            unset($data['display_name']);
        }

        // Map instagram_url → social_links.instagram
        if (array_key_exists('instagram_url', $data)) {
            $socialLinks              = $coach->social_links ?? [];
            $socialLinks['instagram'] = $data['instagram_url'];
            $data['social_links']     = $socialLinks;
            unset($data['instagram_url']);
        }

        // Geocode city if it changed
        if (isset($data['city']) && $data['city'] !== $coach->city) {
            $coords = $this->geocoding->geocode($data['city']);
            if ($coords) {
                $data['lat'] = $coords['lat'];
                $data['lng'] = $coords['lng'];
            }
        }

        $coach->update($data);
        $coach->refresh();

        // Auto-generate slug if missing
        if (empty($coach->slug)) {
            $displayName = $coach->user->name;
            $city        = $coach->city ?? '';
            $coach->slug = $this->slug->generate($displayName, $city, $coach->id);
            $coach->saveQuietly();
        }

        $this->audit->log('PROFILE_UPDATED', $coach->user_id);

        return $coach;
    }
}
