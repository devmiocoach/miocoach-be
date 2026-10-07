<?php

namespace Modules\Users\Tests\E2e\Support;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Actions\CreateCoachProfileAction;
use App\Modules\Users\Database\Seeders\SpecializationsSeeder;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use App\Modules\Users\Models\Review;
use App\Modules\Users\Models\Specialization;

class CoachFixtures
{
    public const EMAIL = 'e2e-coach@miocoach.test';

    public const CLIENT_EMAIL = 'e2e-client@miocoach.test';

    private const SEARCH_EMAILS = [
        'e2e-search-a@miocoach.test', // Sara Bianchi — Yoga, Milano, in_person, 30€, F
        'e2e-search-b@miocoach.test', // Marco Rossi — Crossfit/HIIT, Roma, online, 80€, M
        'e2e-search-c@miocoach.test', // Giulia Verdi — Pilates, Milano, hybrid, 50€, F
    ];

    private const SHOWCASE_EMAILS = [
        'e2e-showcase-booking@miocoach.test',    // solo prenotazione
        'e2e-showcase-message@miocoach.test',    // solo messaggio
        'e2e-showcase-all@miocoach.test',        // tutti i canali
    ];

    public static function createIncompleteCoach(): void
    {
        if (Specialization::query()->where('is_custom', false)->doesntExist()) {
            (new SpecializationsSeeder)->run();
        }

        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'E2E Coach',
                'password' => bcrypt('password'),
                'type' => UserType::COACH,
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole('coach')) {
            $user->assignRole('coach');
        }

        if (! $user->coach) {
            app(CreateCoachProfileAction::class)->handle($user);
            $user->refresh();
        }

        $user->coach->update([
            'onboarding_completed_at' => null,
            'is_published' => false,
            'display_name' => null,
            'tagline' => null,
            'bio' => null,
            'mode' => null,
            'session_duration_default' => '60',
            'cancellation_window_hours' => null,
            'single_session_price_cents' => null,
        ]);

        $user->coach->specializationRecords()->detach();
        $user->coach->availabilities()->delete();
        $user->coach->packages()->delete();
    }

    public static function markOnboardingComplete(): void
    {
        $user = User::where('email', self::EMAIL)->firstOrFail();
        $user->coach->update([
            'onboarding_completed_at' => now(),
            'is_published' => true,
        ]);
    }

    /**
     * Deletes every coach created by the showcase fixtures so the contact
     * widget spec starts from a deterministic set of published profiles.
     */
    public static function clearShowcaseCoaches(): void
    {
        User::query()
            ->where('email', 'like', 'e2e-showcase-%@miocoach.test')
            ->delete();
    }

    /**
     * Creates 3 published coaches used by the contact widget e2e spec, each
     * exercising a different contact-preferences combination:
     *
     *   Ana Moretti  — only direct booking
     *   Leo Fontana  — only direct message
     *   Nina Gallo   — booking + free consultation + direct message
     */
    public static function createShowcaseCoaches(): void
    {
        self::clearShowcaseCoaches();

        self::createShowcaseCoach([
            'email' => self::SHOWCASE_EMAILS[0],
            'name' => 'Ana Moretti',
            'display_name' => 'Ana Moretti',
            'tagline' => 'Personal trainer a Torino',
            'mode' => 'in_person',
            'price_cents' => 4500,
            'direct_booking' => true,
            'free_consultation' => false,
            'direct_message' => false,
        ]);

        self::createShowcaseCoach([
            'email' => self::SHOWCASE_EMAILS[1],
            'name' => 'Leo Fontana',
            'display_name' => 'Leo Fontana',
            'tagline' => 'Coach di crossfit online',
            'mode' => 'online',
            'price_cents' => 6000,
            'direct_booking' => false,
            'free_consultation' => false,
            'direct_message' => true,
        ]);

        self::createShowcaseCoach([
            'email' => self::SHOWCASE_EMAILS[2],
            'name' => 'Nina Gallo',
            'display_name' => 'Nina Gallo',
            'tagline' => 'Yoga e pilates a Bologna',
            'mode' => 'hybrid',
            'price_cents' => 5000,
            'direct_booking' => true,
            'free_consultation' => true,
            'direct_message' => true,
        ]);
    }

    /**
     * Creates the e2e client (role + profile) used to send contact requests
     * from a published coach profile.
     */
    public static function createShowcaseClient(): void
    {
        $user = User::firstOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'name' => 'E2E Client',
                'password' => bcrypt('secretsauce'),
                'type' => UserType::ATLETA->value,
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole(Role::CLIENT->value)) {
            $user->assignRole(Role::CLIENT->value);
        }

        // user_id non è in $fillable: assegnazione diretta (timezone sì).
        if (! $user->client) {
            $client = new Client;
            $client->user_id = $user->id;
            $client->timezone = 'Europe/Rome';
            $client->save();
        }
    }

    /**
     * Returns the showcase coach slugs keyed by fixture email.
     *
     * @return array<string, string>
     */
    public static function showcaseSlugs(): array
    {
        return User::query()
            ->whereIn('email', self::SHOWCASE_EMAILS)
            ->with('coach')
            ->get()
            ->mapWithKeys(fn (User $user) => [
                $user->email => $user->coach->slug,
            ])
            ->all();
    }

    /**
     * Deletes every coach created by the search fixtures and unpublishes any
     * leftover published coach (e.g. the onboarding e2e coach) so the search
     * page starts from a deterministic set of published cards.
     */
    public static function clearSearchCoaches(): void
    {
        User::query()
            ->where('email', 'like', 'e2e-search-%@miocoach.test')
            ->delete();

        Coach::query()
            ->where('is_published', true)
            ->whereNotIn('user_id', User::query()
                ->whereIn('email', self::SEARCH_EMAILS)
                ->pluck('id')
                ->toArray())
            ->update(['is_published' => false]);
    }

    /**
     * Creates the canonical 3 published coaches used by the search e2e specs:
     * distinct specializations, prices, genders, cities, availability slots
     * and published review averages.
     */
    public static function createSearchCoaches(): void
    {
        self::clearSearchCoaches();

        self::createSearchCoach([
            'email' => self::SEARCH_EMAILS[0],
            'name' => 'Sara Bianchi',
            'display_name' => 'Sara Bianchi',
            'tagline' => 'Yoga e pilates a Milano',
            'bio' => 'Insegnante certificata di yoga con 10 anni di esperienza.',
            'city' => 'Milano',
            'province' => 'MI',
            'mode' => 'in_person',
            'gender' => 'female',
            'price_cents' => 3000,
            'specializations' => ['yoga'],
            'availabilities' => [
                ['day' => 1, 'start' => '08:00:00', 'end' => '09:00:00'],
                ['day' => 3, 'start' => '08:00:00', 'end' => '09:00:00'],
            ],
            'ratings' => [5, 5],
        ]);

        self::createSearchCoach([
            'email' => self::SEARCH_EMAILS[1],
            'name' => 'Marco Rossi',
            'display_name' => 'Marco Rossi',
            'tagline' => 'Crossfit coach online',
            'bio' => 'Preparatore atletico specializzato in HIIT e crossfit.',
            'city' => 'Roma',
            'province' => 'RM',
            'mode' => 'online',
            'gender' => 'male',
            'price_cents' => 8000,
            'specializations' => ['crossfit', 'hiit'],
            'availabilities' => [
                ['day' => 2, 'start' => '14:00:00', 'end' => '15:00:00'],
                ['day' => 4, 'start' => '14:00:00', 'end' => '15:00:00'],
            ],
            'ratings' => [4],
        ]);

        self::createSearchCoach([
            'email' => self::SEARCH_EMAILS[2],
            'name' => 'Giulia Verdi',
            'display_name' => 'Giulia Verdi',
            'tagline' => 'Pilates e stretching',
            'bio' => 'Insegnante di pilates e stretching posturale.',
            'city' => 'Milano',
            'province' => 'MI',
            'mode' => 'hybrid',
            'gender' => 'female',
            'price_cents' => 5000,
            'specializations' => ['pilates'],
            'availabilities' => [
                ['day' => 5, 'start' => '19:00:00', 'end' => '20:00:00'],
            ],
            'ratings' => [5, 4],
        ]);
    }

    /**
     * Creates 25 published coaches with distinct names so the search page
     * paginates (limit 20) and infinite scroll can load a second page.
     */
    public static function createScrollCoaches(): void
    {
        self::clearSearchCoaches();

        for ($i = 1; $i <= 25; $i++) {
            self::createSearchCoach([
                'email' => sprintf('e2e-search-%02d@miocoach.test', $i),
                'name' => "Coach $i",
                'display_name' => "Coach $i",
                'tagline' => 'Personal trainer',
                'bio' => 'Personal trainer disponibile a domicilio.',
                'city' => 'Torino',
                'province' => 'TO',
                'mode' => 'online',
                'gender' => 'male',
                'price_cents' => 4500,
                'specializations' => ['functional-training'],
                'availabilities' => [
                    ['day' => 2, 'start' => '10:00:00', 'end' => '11:00:00'],
                ],
                'ratings' => [],
            ]);
        }
    }

    private static function ensureSpecializations(array $slugs): void
    {
        $names = [
            'yoga' => 'Yoga',
            'crossfit' => 'Crossfit',
            'hiit' => 'HIIT',
            'pilates' => 'Pilates',
            'functional-training' => 'Functional training',
        ];

        foreach ($slugs as $slug) {
            Specialization::firstOrCreate(
                ['slug' => $slug],
                ['name' => $names[$slug] ?? ucfirst($slug), 'is_custom' => false],
            );
        }
    }

    private static function createShowcaseCoach(array $data): void
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt('secretsauce'),
            'type' => UserType::COACH->value,
            'email_verified_at' => now(),
        ]);

        if (! $user->hasRole('coach')) {
            $user->assignRole('coach');
        }

        // user_id, slug e is_verified sono fuori da $fillable: assegnazione
        // diretta (come in CreateCoachProfileAction), il resto via fill().
        $coach = new Coach;
        $coach->user_id = $user->id;
        $coach->slug = str($data['display_name'])->slug()->append('-', (string) $user->id);
        $coach->is_verified = false;
        $coach->fill([
            'display_name' => $data['display_name'],
            'tagline' => $data['tagline'],
            'bio' => 'Bio professionale del coach per test automatizzato.',
            'city' => 'Torino',
            'mode' => $data['mode'],
            'single_session_price_cents' => $data['price_cents'],
            'session_duration_default' => '60',
            'languages' => ['it'],
            'is_visible' => true,
            'is_published' => true,
            'onboarding_completed_at' => now(),
            'accepts_direct_booking' => $data['direct_booking'],
            'accepts_free_consultation' => $data['free_consultation'],
            'accepts_direct_message' => $data['direct_message'],
        ]);
        $coach->save();
    }

    private static function createSearchCoach(array $data): void
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt('password'),
            'type' => UserType::COACH->value,
            'email_verified_at' => now(),
        ]);

        if (! $user->hasRole('coach')) {
            $user->assignRole('coach');
        }

        // user_id, slug e is_verified sono fuori da $fillable: assegnazione
        // diretta (come in CreateCoachProfileAction), il resto via fill().
        $coach = new Coach;
        $coach->user_id = $user->id;
        $coach->slug = str($data['display_name'])->slug()->append('-', (string) $user->id);
        $coach->is_verified = false;
        $coach->fill([
            'display_name' => $data['display_name'],
            'tagline' => $data['tagline'],
            'bio' => $data['bio'],
            'city' => $data['city'],
            'province' => $data['province'],
            'mode' => $data['mode'],
            'gender' => $data['gender'],
            'single_session_price_cents' => $data['price_cents'],
            'session_duration_default' => '60',
            'languages' => ['it'],
            'is_visible' => true,
            'is_published' => true,
            'onboarding_completed_at' => now(),
        ]);
        $coach->save();

        $coach = $user->coach;

        self::ensureSpecializations($data['specializations']);

        $coach->specializationRecords()->sync(
            Specialization::query()->whereIn('slug', $data['specializations'])->pluck('id'),
        );

        foreach ($data['availabilities'] as $slot) {
            CoachAvailability::query()->create([
                'coach_id' => $coach->id,
                'day_of_week' => $slot['day'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'duration_minutes' => 60,
                'is_recurring' => true,
            ]);
        }

        foreach ($data['ratings'] as $rating) {
            Review::query()->create([
                'coach_id' => $coach->id,
                'rating' => $rating,
                'is_verified' => false,
                'published_at' => now(),
            ]);
        }
    }
}
