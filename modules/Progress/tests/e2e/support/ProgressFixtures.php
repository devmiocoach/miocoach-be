<?php

namespace Modules\Progress\Tests\E2e\Support;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Actions\CreateCoachProfileAction;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Modules\Progress\Enums\MetricType;
use Modules\Progress\Models\BodyMeasurement;

class ProgressFixtures
{
    public const COACH_EMAIL = 'e2e-progress-coach@miocoach.test';

    public const CLIENT_EMAIL = 'e2e-progress-client@miocoach.test';

    /**
     * @return array{coach_email: string, client_id: int}
     */
    public static function setup(): array
    {
        $coachUser = User::firstOrCreate(
            ['email' => self::COACH_EMAIL],
            [
                'name' => 'Progress Coach',
                'password' => bcrypt('password'),
                'type' => UserType::COACH,
                'email_verified_at' => now(),
            ],
        );

        if (! $coachUser->hasRole('coach')) {
            $coachUser->assignRole('coach');
        }

        if (! $coachUser->coach) {
            app(CreateCoachProfileAction::class)->handle($coachUser);
            $coachUser->refresh();
        }

        /** @var Coach $coach */
        $coach = $coachUser->coach;
        $coach->update(['onboarding_completed_at' => now()]);

        $clientUser = User::firstOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'name' => 'Progress Client',
                'password' => bcrypt('password'),
                'type' => UserType::ATLETA,
                'email_verified_at' => now(),
            ],
        );

        if (! $clientUser->hasRole('client')) {
            $clientUser->assignRole('client');
        }

        if (! $clientUser->client) {
            $clientUser->client()->create();
            $clientUser->refresh();
        }

        /** @var Client $client */
        $client = $clientUser->client;

        if (! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            $coach->athletes()->attach($client->id, [
                'status' => 'active',
                'started_at' => now(),
            ]);
        }

        BodyMeasurement::firstOrCreate(
            [
                'client_id' => $client->id,
                'coach_id' => $coach->id,
                'metric_type' => MetricType::Weight->value,
                'measured_at' => now()->subDays(7),
            ],
            ['value' => 80.5],
        );

        BodyMeasurement::firstOrCreate(
            [
                'client_id' => $client->id,
                'coach_id' => $coach->id,
                'metric_type' => MetricType::Weight->value,
                'measured_at' => now(),
            ],
            ['value' => 79.8],
        );

        return [
            'coach_email' => self::COACH_EMAIL,
            'client_id' => $client->id,
        ];
    }
}
