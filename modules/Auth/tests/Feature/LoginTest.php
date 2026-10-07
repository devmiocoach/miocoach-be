<?php

namespace Modules\Auth\Tests\Feature;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'type' => 'atleta',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_with_wrong_password_returns_error(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'type' => 'atleta',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('error');
    }

    public function test_login_validates_email_is_required(): void
    {
        $response = $this->post(route('login'), [
            'password' => 'password',
        ]);

        $response->assertInvalid('email');
    }

    public function test_login_validates_password_is_required(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
        ]);

        $response->assertInvalid('password');
    }

    public function test_login_validates_email_format(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertInvalid('email');
    }

    public function test_login_fires_lockout_event_after_five_failed_attempts(): void
    {
        Event::fake([Lockout::class]);

        $user = $this->createUser();

        // Make 5 failed attempts to build up the rate limiter
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
                'type' => 'atleta',
            ]);
        }

        // 6th attempt triggers the lockout
        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'type' => 'atleta',
        ]);

        Event::assertDispatched(Lockout::class);
        $response->assertSessionHas('error');
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = $this->createUser();

        $this->assertNull($user->last_login_at);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'type' => 'atleta',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_logout_invalidates_session_and_redirects(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
    }

    public function test_logout_redirects_to_home(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
    }

    public function test_login_validates_type_is_required(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertInvalid('type');
    }

    public function test_coach_can_login_as_coach(): void
    {
        $user = User::factory()->create(['type' => UserType::COACH]);
        $user->assignRole(Role::COACH);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'type' => 'coach',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_coach_cannot_login_as_atleta(): void
    {
        $user = User::factory()->create(['type' => UserType::COACH]);
        $user->assignRole(Role::COACH);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'type' => 'atleta',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('error');
    }

    public function test_atleta_can_login_as_atleta(): void
    {
        $user = User::factory()->create(['type' => UserType::ATLETA]);
        $user->assignRole(Role::CLIENT);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'type' => 'atleta',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_atleta_cannot_login_as_coach(): void
    {
        $user = User::factory()->create(['type' => UserType::ATLETA]);
        $user->assignRole(Role::CLIENT);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'type' => 'coach',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('error');
    }
}
