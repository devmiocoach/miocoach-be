<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Illuminate\Auth\Events\Registered;
use Spatie\Permission\Models\Role;
use Tests\Feature\Users\Concerns\WithClientUser;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachListingTest extends TestCase
{
    use WithClientUser, WithCoachUser;

    public function test_client_profile_created_on_registration(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->assertNull($user->fresh()->client);

        event(new Registered($user));

        $this->assertNotNull($user->fresh()->client);
        $this->assertNull($user->fresh()->client->coach_id);
    }

    public function test_registration_listener_does_not_duplicate_client(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $existing            = new Client();
        $existing->user_id   = $user->id;
        $existing->joined_at = now();
        $existing->save();

        event(new Registered($user));

        $this->assertCount(1, Client::where('user_id', $user->id)->get());
    }
}
