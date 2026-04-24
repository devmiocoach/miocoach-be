<?php

namespace Tests\Feature\Users\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

trait WithClientUser
{
    use RefreshDatabase;

    protected function createStandaloneClient(array $userAttrs = []): array
    {
        $user = User::factory()->create(array_merge(
            ['email_verified_at' => now()],
            $userAttrs
        ));

        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $client            = new Client();
        $client->user_id   = $user->id;
        $client->joined_at = now();
        $client->save();

        return [$user, $client];
    }

    protected function actingAsClient(User $user): static
    {
        return $this->actingAs($user)->withoutMiddleware([
            \App\Modules\Auth\Http\Middleware\JwtAuthenticate::class,
            \App\Modules\Auth\Http\Middleware\RequireVerified::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }
}
