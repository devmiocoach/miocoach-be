<?php

namespace Modules\Checkins\Tests\Feature;

use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Checkins\Enums\CheckinFrequency;
use Modules\Checkins\Models\CheckinForm;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_form_casts_and_relations(): void
    {
        $coach = Coach::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create([
            'frequency' => CheckinFrequency::Weekly,
            'questions' => [['id' => 'q1', 'text' => 'Come va?', 'type' => 'text', 'required' => true, 'order' => 1]],
        ]);

        $this->assertInstanceOf(CheckinFrequency::class, $form->frequency);
        $this->assertIsArray($form->questions);
        $this->assertTrue($coach->checkinForms->contains($form));
    }
}
