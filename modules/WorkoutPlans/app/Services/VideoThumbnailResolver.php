<?php

namespace Modules\WorkoutPlans\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class VideoThumbnailResolver
{
    /**
     * Ask the provider's oEmbed endpoint for a thumbnail.
     * A missing thumbnail never blocks a save, so every failure returns null.
     */
    public function resolve(?string $videoUrl): ?string
    {
        if ($videoUrl === null || $videoUrl === '') {
            return null;
        }

        $host = strtolower((string) parse_url($videoUrl, PHP_URL_HOST));

        $endpoint = match (true) {
            str_contains($host, 'youtu') => 'https://www.youtube.com/oembed',
            str_contains($host, 'vimeo') => 'https://vimeo.com/api/oembed.json',
            default => null,
        };

        if ($endpoint === null) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get($endpoint, ['url' => $videoUrl, 'format' => 'json']);

            if ($response->failed()) {
                return null;
            }

            $thumbnail = $response->json('thumbnail_url');

            return is_string($thumbnail) ? $thumbnail : null;
        } catch (Throwable) {
            return null;
        }
    }
}
