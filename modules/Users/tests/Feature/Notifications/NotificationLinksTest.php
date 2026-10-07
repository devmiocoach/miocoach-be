<?php

namespace App\Modules\Users\Tests\Feature\Notifications;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Modules\Users\Notifications\CoachContactRequestNotification;
use App\Modules\Users\Notifications\CoachInvitationNotification;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel ships a default `app.frontend_url` of http://localhost:3000, which is
 * unreachable in this stack. Links must follow the configured frontend URL,
 * which falls back to APP_URL.
 */
class NotificationLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    public function test_invitation_link_uses_the_configured_frontend_url(): void
    {
        config(['app.frontend_url' => 'https://app.miocoach.test/']);

        $coach = Coach::factory()->create();
        $invitation = new CoachInvitation([
            'coach_id' => $coach->id,
            'email' => 'athlete@example.com',
            'token' => 'tok123',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $actionUrl = (new CoachInvitationNotification($invitation, $coach))
            ->toMail($coach->user)
            ->actionUrl;

        $this->assertSame('https://app.miocoach.test/register/invite/tok123', $actionUrl);
    }

    public function test_contact_request_link_uses_the_configured_frontend_url(): void
    {
        config(['app.frontend_url' => 'https://app.miocoach.test/']);

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();

        $actionUrl = (new CoachContactRequestNotification($client, $coach, 'Ciao'))
            ->toMail($coach->user)
            ->actionUrl;

        $this->assertSame('https://app.miocoach.test/coach/contact-requests', $actionUrl);
    }

    public function test_frontend_url_falls_back_to_app_url(): void
    {
        $this->assertSame(config('app.url'), config('app.frontend_url'));
        $this->assertNotSame('http://localhost:3000', config('app.frontend_url'));
    }
}
