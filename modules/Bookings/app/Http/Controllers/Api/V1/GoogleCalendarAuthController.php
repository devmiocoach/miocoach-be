<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Coach;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GoogleCalendarAuthController extends Controller
{
    public function getAuthUrl(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $client = $this->makeClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $state = Str::random(32);
        Cache::put("gcal_oauth_state:{$state}", $coach->id, now()->addMinutes(10));

        $client->setState($state);

        return response()->json(['auth_url' => $client->createAuthUrl()]);
    }

    public function handleCallback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');

        $frontendUrl = config('app.url');

        if (! $state || ! $code) {
            return redirect("{$frontendUrl}/settings?calendar=error&reason=missing_params");
        }

        $coachId = Cache::pull("gcal_oauth_state:{$state}");
        if (! $coachId) {
            return redirect("{$frontendUrl}/settings?calendar=error&reason=invalid_state");
        }

        $coach = Coach::find($coachId);
        if (! $coach) {
            return redirect("{$frontendUrl}/settings?calendar=error&reason=coach_not_found");
        }

        try {
            $client = $this->makeClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                return redirect("{$frontendUrl}/settings?calendar=error&reason=token_exchange_failed");
            }

            $coach->google_calendar_access_token = Crypt::encryptString($token['access_token']);
            $coach->google_calendar_refresh_token = isset($token['refresh_token'])
                ? Crypt::encryptString($token['refresh_token'])
                : $coach->google_calendar_refresh_token;
            $coach->google_calendar_token_expires_at = now()->addSeconds((int) ($token['expires_in'] ?? 3600));
            $coach->save();
        } catch (\Throwable $e) {
            return redirect("{$frontendUrl}/settings?calendar=error&reason=exception");
        }

        return redirect("{$frontendUrl}/settings?calendar=connected");
    }

    public function disconnect(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        if ($coach->google_calendar_access_token) {
            try {
                $client = $this->makeClient();
                $client->setAccessToken([
                    'access_token' => Crypt::decryptString($coach->google_calendar_access_token),
                ]);
                $client->revokeToken();
            } catch (\Throwable) {
                // Best-effort revoke — clear tokens regardless.
            }
        }

        $coach->google_calendar_access_token = null;
        $coach->google_calendar_refresh_token = null;
        $coach->google_calendar_token_expires_at = null;
        $coach->google_calendar_id = null;
        $coach->save();

        return response()->json(['message' => 'Google Calendar disconnesso con successo.']);
    }

    public function status(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        return response()->json([
            'connected' => $coach->google_calendar_access_token !== null,
            'calendar_id' => $coach->google_calendar_id ?? 'primary',
        ]);
    }

    private function makeClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId(config('bookings.google_calendar.client_id'));
        $client->setClientSecret(config('bookings.google_calendar.client_secret'));
        $client->setRedirectUri(config('bookings.google_calendar.redirect_uri'));
        $client->addScope(Calendar::CALENDAR_EVENTS);

        return $client;
    }
}
