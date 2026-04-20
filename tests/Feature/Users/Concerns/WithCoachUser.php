<?php

namespace Tests\Feature\Users\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

trait WithCoachUser
{
    use RefreshDatabase;

    protected function createCoachUser(array $coachAttributes = []): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']);
        $user->assignRole($role);

        $coach = Coach::forceCreate(array_merge([
            'user_id' => $user->id,
            'slug'    => 'test-coach-' . $user->id,
        ], $coachAttributes));

        return [$user, $coach];
    }

    protected function actingAsCoach(User $user): static
    {
        return $this->actingAs($user)->withoutMiddleware([
            \App\Modules\Auth\Http\Middleware\JwtAuthenticate::class,
            \App\Modules\Auth\Http\Middleware\RequireVerified::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }

    protected function createClientForCoach(
        \App\Modules\Users\Models\Coach $coach,
        array $userAttrs = [],
        array $clientAttrs = []
    ): array {
        $clientUser = \App\Models\User::factory()->create(array_merge(
            ['email_verified_at' => now()],
            $userAttrs
        ));

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $clientUser->assignRole($role);

        $client            = new \App\Modules\Users\Models\Client();
        $client->user_id   = $clientUser->id;
        $client->coach_id  = $coach->id;
        $client->joined_at = now();
        foreach ($clientAttrs as $key => $value) {
            $client->$key = $value;
        }
        $client->save();

        return [$clientUser, $client];
    }
}
