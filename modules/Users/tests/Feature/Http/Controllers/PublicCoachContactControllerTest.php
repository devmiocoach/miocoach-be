<?php

namespace Modules\Users\Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicCoachContactControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    private function makeClientUser(): User
    {
        $client = Client::factory()->create();
        $user = $client->user;
        $user->assignRole(Role::CLIENT);

        return $user;
    }

    public function test_client_can_send_a_message_when_coach_accepts_it(): void
    {
        Notification::fake();
        $coach = Coach::factory()->create(['is_published' => true, 'accepts_direct_message' => true]);
        $user = $this->makeClientUser();

        $this->actingAs($user)
            ->post(route('coach.public.contact', $coach->slug), ['type' => 'message', 'message' => 'Ciao!'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('coach_contact_requests', [
            'coach_id' => $coach->id,
            'client_id' => $user->client->id,
            'type' => 'message',
            'status' => 'pending',
        ]);
    }

    public function test_free_consultation_is_rejected_when_coach_does_not_offer_it(): void
    {
        $coach = Coach::factory()->create(['is_published' => true, 'accepts_free_consultation' => false]);

        $this->actingAs($this->makeClientUser())
            ->post(route('coach.public.contact', $coach->slug), ['type' => 'free_consultation'])
            ->assertSessionHasErrors(['type']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $coach = Coach::factory()->create(['is_published' => true, 'accepts_direct_message' => true]);

        $this->post(route('coach.public.contact', $coach->slug), ['type' => 'message'])
            ->assertRedirect(route('login'));
    }

    public function test_guest_login_link_remembers_intended_url(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);

        $this->get(route('coach.public.login', $coach->slug))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('coach.public.show', $coach->slug));
    }

    public function test_booking_page_is_hidden_when_direct_booking_is_off(): void
    {
        $coach = Coach::factory()->create(['is_published' => true, 'accepts_direct_booking' => false]);

        $this->get(route('coach.public.book', $coach->slug))->assertNotFound();
    }
}
