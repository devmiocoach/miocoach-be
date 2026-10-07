<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Resources\PublicCoachResource;
use App\Modules\Users\Models\Coach;
use Inertia\Inertia;
use Inertia\Response;

class PublicCoachPageController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $coach = Coach::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $coach->load([
            'user',
            'verifiedCertifications',
            'activePackages',
            'availabilities',
        ]);

        $coach->loadCount('publishedReviews as reviews_count');
        $coach->loadAvg('publishedReviews as avg_rating', 'rating');

        return Inertia::render('Users::Public/CoachProfile', [
            'coach' => (new PublicCoachResource($coach))->resolve(),
        ])->withSSR();
    }
}
