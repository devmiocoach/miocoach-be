<?php

namespace Modules\Chat\Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Chat\Models\Conversation;
use Tests\TestCase;

class CoachChatPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_sees_chat_page(): void
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);

        $conversation = Conversation::factory()->create([
            'coach_id' => $coach->id,
        ]);

        $this->actingAs($coach->user)
            ->withoutMiddleware(EnsureOnboardingComplete::class)
            ->get('/coach/chat')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Chat::Coach/Index', false)
                ->has('conversations.data', 1)
                ->where('conversations.data.0.id', $conversation->id)
                ->where('conversations.data.0.coach.id', $coach->id));
    }
}
