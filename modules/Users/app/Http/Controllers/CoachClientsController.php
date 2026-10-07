<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Services\EncryptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoachClientsController extends Controller
{
    public function index(Request $request): Response
    {
        $coach = $request->user()->coach;

        $allClients = $coach->athletes()
            ->with(['user', 'upcomingBooking'])
            ->orderBy('clients.id', 'desc')
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->user?->name,
                'email' => $client->user?->email,
                'phone' => $client->phone,
                'avatar_url' => $client->user?->profile_photo_url,
                'tags' => $client->tags ?? [],
                'status' => $client->pivot->status ?? 'active',
                'subscription_expires_at' => $client->subscription_expires_at?->toISOString(),
                'subscription_sessions_remaining' => $client->subscription_sessions_remaining,
                'next_booking' => $client->upcomingBooking ? [
                    'id' => $client->upcomingBooking->id,
                    'starts_at' => $client->upcomingBooking->starts_at?->toISOString(),
                ] : null,
            ]);

        $availableTags = $allClients
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Inertia::render('Users::Coach/Clients/Index', [
            'clients' => $allClients,
            'availableTags' => $availableTags,
        ])->withoutSSR();
    }

    public function show(Request $request, Client $client, EncryptionService $encryption): Response
    {
        $coach = $request->user()->coach;

        abort_if(! $coach->athletes()->where('athlete_id', $client->id)->exists(), 403);

        $client->load([
            'user',
            'notes' => fn ($q) => $q->with('coach.user')->limit(20),
            'anamnesis',
            'upcomingBooking',
            'recentSessions',
        ]);

        $decryptedAnamnesis = null;
        if ($client->anamnesis) {
            try {
                $decryptedAnamnesis = $encryption->decrypt(
                    $client->anamnesis->content_encrypted,
                    $client->anamnesis->iv,
                    $client->anamnesis->tag
                );
            } catch (\RuntimeException) {
                $decryptedAnamnesis = null;
            }
        }

        $pivot = $coach->athletes()->where('athlete_id', $client->id)->first()?->pivot;

        return Inertia::render('Users::Coach/Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->user?->name,
                'email' => $client->user?->email,
                'phone' => $client->phone,
                'avatar_url' => $client->user?->profile_photo_url,
                'tags' => $client->tags ?? [],
                'status' => $pivot?->status ?? 'active',
                'birth_date' => $client->birth_date?->toDateString(),
                'gender' => $client->gender,
                'height_cm' => $client->height_cm,
                'weight_kg' => $client->weight_kg,
                'goals' => $client->goals,
                'objective' => $client->objective,
                'fitness_level' => $client->fitness_level,
                'joined_at' => $client->joined_at?->toISOString(),
                'subscription_expires_at' => $client->subscription_expires_at?->toISOString(),
                'subscription_sessions_remaining' => $client->subscription_sessions_remaining,
                'anamnesis' => $decryptedAnamnesis,
                'next_booking' => $client->upcomingBooking ? [
                    'id' => $client->upcomingBooking->id,
                    'starts_at' => $client->upcomingBooking->starts_at?->toISOString(),
                    'service_type' => $client->upcomingBooking->service_type?->value,
                ] : null,
                'recent_sessions' => $client->recentSessions?->map(fn ($b) => [
                    'id' => $b->id,
                    'starts_at' => $b->starts_at?->toISOString(),
                    'service_type' => $b->service_type?->value,
                    'status' => $b->status->value,
                ]) ?? [],
            ],
            'notes' => $client->notes->map(fn ($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'coach_name' => $n->coach?->user?->name,
                'created_at' => $n->created_at?->toISOString(),
            ]),
        ])->withoutSSR();
    }
}
