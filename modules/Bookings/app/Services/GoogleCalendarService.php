<?php

namespace Modules\Bookings\Services;

use App\Modules\Users\Models\Coach;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;

class GoogleCalendarService implements CalendarServiceContract
{
    public function createEvent(Booking $booking): ?string
    {
        $client = $this->getClientForCoach($booking->coach);
        if (! $client) {
            return null;
        }

        try {
            $service = new Calendar($client);
            $event = $this->buildEvent($booking);
            $calendarId = $booking->coach->google_calendar_id ?? 'primary';
            $created = $service->events->insert($calendarId, $event);

            return $created->getId();
        } catch (\Throwable $e) {
            Log::warning('[GoogleCalendar] createEvent failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateEvent(Booking $booking): void
    {
        if (! $booking->google_calendar_event_id) {
            return;
        }

        $client = $this->getClientForCoach($booking->coach);
        if (! $client) {
            return;
        }

        try {
            $service = new Calendar($client);
            $calendarId = $booking->coach->google_calendar_id ?? 'primary';
            $existing = $service->events->get($calendarId, $booking->google_calendar_event_id);
            $updated = $this->applyEventData($existing, $booking);
            $service->events->update($calendarId, $booking->google_calendar_event_id, $updated);
        } catch (\Throwable $e) {
            Log::warning('[GoogleCalendar] updateEvent failed', [
                'booking_id' => $booking->id,
                'event_id' => $booking->google_calendar_event_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteEvent(Booking $booking): void
    {
        if (! $booking->google_calendar_event_id) {
            return;
        }

        $client = $this->getClientForCoach($booking->coach);
        if (! $client) {
            return;
        }

        try {
            $service = new Calendar($client);
            $calendarId = $booking->coach->google_calendar_id ?? 'primary';
            $service->events->delete($calendarId, $booking->google_calendar_event_id);
        } catch (\Throwable $e) {
            Log::warning('[GoogleCalendar] deleteEvent failed', [
                'booking_id' => $booking->id,
                'event_id' => $booking->google_calendar_event_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getClientForCoach(Coach $coach): ?GoogleClient
    {
        if (! $coach->google_calendar_access_token || ! $coach->google_calendar_refresh_token) {
            return null;
        }

        $clientId = config('bookings.google_calendar.client_id');
        $clientSecret = config('bookings.google_calendar.client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        try {
            $client = new GoogleClient;
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->setRedirectUri(config('bookings.google_calendar.redirect_uri'));
            $client->addScope(Calendar::CALENDAR_EVENTS);

            $expiresAt = $coach->google_calendar_token_expires_at;
            $expiresIn = $expiresAt ? max(0, now()->diffInSeconds($expiresAt, false)) : 0;

            $client->setAccessToken([
                'access_token' => Crypt::decryptString($coach->google_calendar_access_token),
                'refresh_token' => Crypt::decryptString($coach->google_calendar_refresh_token),
                'expires_in' => $expiresIn,
                'created' => $expiresAt ? $expiresAt->timestamp - 3600 : 0,
            ]);

            if ($client->isAccessTokenExpired()) {
                $newToken = $client->fetchAccessTokenWithRefreshToken(
                    Crypt::decryptString($coach->google_calendar_refresh_token)
                );

                if (isset($newToken['error'])) {
                    Log::warning('[GoogleCalendar] token refresh failed', [
                        'coach_id' => $coach->id,
                        'error' => $newToken['error'],
                    ]);

                    return null;
                }

                $coach->google_calendar_access_token = Crypt::encryptString($newToken['access_token']);
                if (isset($newToken['refresh_token'])) {
                    $coach->google_calendar_refresh_token = Crypt::encryptString($newToken['refresh_token']);
                }
                $coach->google_calendar_token_expires_at = now()->addSeconds((int) ($newToken['expires_in'] ?? 3600));
                $coach->save();
            }

            return $client;
        } catch (\Throwable $e) {
            Log::warning('[GoogleCalendar] getClientForCoach failed', [
                'coach_id' => $coach->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildEvent(Booking $booking): Event
    {
        $event = new Event;

        $guestName = $booking->client?->user?->name ?? $booking->guest_name ?? 'Cliente';
        $event->setSummary("Sessione — {$guestName}");

        $description = "Prenotazione #{$booking->id}";
        if ($booking->service_type) {
            $description .= "\nServizio: {$booking->service_type->value}";
        }
        if ($booking->notes) {
            $description .= "\nNote: {$booking->notes}";
        }
        $event->setDescription($description);

        $start = new EventDateTime;
        $start->setDateTime($booking->starts_at->toRfc3339String());
        $start->setTimeZone(config('app.timezone', 'Europe/Rome'));
        $event->setStart($start);

        $end = new EventDateTime;
        $end->setDateTime($booking->ends_at->toRfc3339String());
        $end->setTimeZone(config('app.timezone', 'Europe/Rome'));
        $event->setEnd($end);

        $attendees = [];
        if ($booking->client?->user?->email) {
            $attendee = new EventAttendee;
            $attendee->setEmail($booking->client->user->email);
            $attendees[] = $attendee;
        } elseif ($booking->guest_email) {
            $attendee = new EventAttendee;
            $attendee->setEmail($booking->guest_email);
            $attendees[] = $attendee;
        }

        if (! empty($attendees)) {
            $event->setAttendees($attendees);
        }

        return $event;
    }

    private function applyEventData(Event $event, Booking $booking): Event
    {
        $guestName = $booking->client?->user?->name ?? $booking->guest_name ?? 'Cliente';
        $event->setSummary("Sessione — {$guestName}");

        $start = new EventDateTime;
        $start->setDateTime($booking->starts_at->toRfc3339String());
        $start->setTimeZone(config('app.timezone', 'Europe/Rome'));
        $event->setStart($start);

        $end = new EventDateTime;
        $end->setDateTime($booking->ends_at->toRfc3339String());
        $end->setTimeZone(config('app.timezone', 'Europe/Rome'));
        $event->setEnd($end);

        return $event;
    }
}
