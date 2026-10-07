<?php

namespace Modules\CoachCommerce\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\CoachCommerce\Filament\RelationManagers\SubscriptionPlansRelationManager;
use Modules\CoachCommerce\Models\SubscriptionPlan;
use Modules\Users\Filament\Resources\CoachResource;
use Modules\Users\Filament\Resources\CoachResource\Pages\ViewCoach;
use Tests\TestCase;

class CoachRelationManagersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole(Role::ADMIN);
        $this->coach = Coach::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
    }

    public function test_coach_resource_registers_subscription_plans_relation_manager(): void
    {
        $this->assertContains(SubscriptionPlansRelationManager::class, CoachResource::getRelations());
    }

    public function test_subscription_plans_relation_manager_lists_only_this_coach_plans(): void
    {
        $plans = SubscriptionPlan::factory()->count(2)->create(['coach_id' => $this->coach->id]);
        $other = SubscriptionPlan::factory()->create();

        Livewire::test(SubscriptionPlansRelationManager::class, [
            'ownerRecord' => $this->coach,
            'pageClass' => ViewCoach::class,
        ])
            ->assertOk()
            ->loadTable()
            ->assertCanSeeTableRecords($plans)
            ->assertCanNotSeeTableRecords([$other]);
    }
}
