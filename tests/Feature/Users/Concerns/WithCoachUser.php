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

        $coach = Coach::create(array_merge([
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
}
