<?php

namespace Modules\Chat\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Chat\Filament\Resources\Conversations\ConversationResource;
use Modules\Chat\Filament\Resources\Conversations\Pages\ListConversations;
use Modules\Chat\Filament\Resources\Conversations\Pages\ViewConversation;
use Modules\Chat\Models\Conversation;
use Tests\TestCase;

class ChatFilamentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(Role::ADMIN);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs($admin);
    }

    private function conversation(): Conversation
    {
        return Conversation::factory()->create([
            'coach_id' => Coach::factory()->create()->id,
            'client_id' => Client::factory()->create()->id,
        ]);
    }

    public function test_admin_can_list_conversations(): void
    {
        $conversations = collect([$this->conversation(), $this->conversation()]);

        Livewire::test(ListConversations::class)
            ->assertCanSeeTableRecords($conversations);
    }

    public function test_admin_can_view_a_conversation(): void
    {
        $conversation = $this->conversation();
        $conversation->messages()->create([
            'sender_user_id' => $conversation->coach->user->id,
            'type' => 'text',
            'body' => 'ciao admin',
        ]);

        Livewire::test(ViewConversation::class, ['record' => $conversation->id])
            ->assertOk();
    }

    public function test_conversation_resource_is_read_only(): void
    {
        $this->assertFalse(ConversationResource::canCreate());
        $this->assertFalse(ConversationResource::canEdit($this->conversation()));
        $this->assertFalse(ConversationResource::canDelete($this->conversation()));
    }
}
