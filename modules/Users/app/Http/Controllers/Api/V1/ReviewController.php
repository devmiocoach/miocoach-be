<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Requests\StoreReviewRequest;
use App\Modules\Users\Http\Resources\ReviewResource;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Models\Booking;

class ReviewController extends Controller
{
    public function index(Coach $coach, Request $request): JsonResponse
    {
        $reviews = $coach->publishedReviews()
            ->with('client.user')
            ->latest()
            ->paginate(min((int) $request->input('limit', 10), 50));

        $avgRating = $coach->publishedReviews()->avg('rating');

        $responseData = ReviewResource::collection($reviews)->response()->getData(true);

        return response()->json([
            ...$responseData,
            'meta' => [
                ...($responseData['meta'] ?? []),
                'avg_rating' => $avgRating ? round((float) $avgRating, 1) : null,
                'reviews_count' => $reviews->total(),
            ],
        ]);
    }

    public function store(Coach $coach, StoreReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $client = $user->client;

        if (! $client) {
            return response()->json(['message' => 'Solo i clienti possono lasciare recensioni.'], 403);
        }

        if ($client->user_id === $coach->user_id) {
            return response()->json(['message' => 'Non puoi recensire te stesso.'], 422);
        }

        $existingReview = Review::where('coach_id', $coach->id)
            ->where('client_id', $client->id)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'Hai già lasciato una recensione per questo coach.'], 422);
        }

        $isVerified = false;
        $verifiedBookingId = null;
        if ($request->filled('booking_id')) {
            $booking = Booking::where('id', $request->input('booking_id'))
                ->where('coach_id', $coach->id)
                ->where('client_id', $client->id)
                ->where('status', 'completed')
                ->first();

            if ($booking) {
                $isVerified = true;
                $verifiedBookingId = $booking->id;
            }
        }

        $review = Review::create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'booking_id' => $verifiedBookingId,
            'rating' => $request->input('rating'),
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'is_verified' => $isVerified,
            'published_at' => now(),
        ]);

        return response()->json([
            'data' => new ReviewResource($review->load('client.user')),
        ], 201);
    }
}
