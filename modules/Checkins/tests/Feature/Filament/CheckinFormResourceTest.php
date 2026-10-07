<?php

namespace Modules\Checkins\Tests\Feature\Filament;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Checkins\Filament\Resources\CheckinForms\Pages\ListCheckinForms;
use Modules\Checkins\Models\CheckinForm;
use Tests\TestCase;

class CheckinFormResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole(Role::ADMIN);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
    }

    public function test_list_page_renders_forms(): void
    {
        $coach = Coach::factory()->create();
        $forms = CheckinForm::factory()->for($coach)->count(2)->create();

        Livewire::test(ListCheckinForms::class)
            ->assertOk()
            ->assertCanSeeTableRecords($forms);
    }
}
