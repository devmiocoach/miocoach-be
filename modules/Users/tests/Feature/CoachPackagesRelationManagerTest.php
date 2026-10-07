<?php

namespace App\Modules\Users\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachPackage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Users\Filament\Resources\CoachResource;
use Modules\Users\Filament\Resources\CoachResource\Pages\ViewCoach;
use Modules\Users\Filament\Resources\CoachResource\RelationManagers\PackagesRelationManager;
use Tests\TestCase;

class CoachPackagesRelationManagerTest extends TestCase
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

    public function test_coach_resource_registers_packages_relation_manager(): void
    {
        $this->assertContains(PackagesRelationManager::class, CoachResource::getRelations());
    }

    public function test_packages_relation_manager_lists_only_this_coach_packages(): void
    {
        $packages = CoachPackage::factory()->count(3)->create(['coach_id' => $this->coach->id]);
        $other = CoachPackage::factory()->create();

        Livewire::test(PackagesRelationManager::class, [
            'ownerRecord' => $this->coach,
            'pageClass' => ViewCoach::class,
        ])
            ->assertOk()
            ->loadTable()
            ->assertCanSeeTableRecords($packages)
            ->assertCanNotSeeTableRecords([$other]);
    }
}
