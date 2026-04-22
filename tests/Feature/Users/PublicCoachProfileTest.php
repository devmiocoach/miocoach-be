<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\Certification;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class PublicCoachProfileTest extends TestCase
{
    use WithCoachUser;

    public function test_anyone_can_view_published_coach_public_profile(): void
    {
        [$user, $coach] = $this->createCoachUser([], [
            'bio'               => 'Pro coach',
            'tagline'           => 'Transform yourself',
            'specializations'   => ['yoga'],
            'city'              => 'Roma',
            'mode'              => 'online',
            'price_per_session' => 45.00,
            'is_published'      => true,
        ]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Pro coach')
            ->assertJsonPath('data.city', 'Roma')
            ->assertJsonStructure(['data' => ['seo' => ['title', 'description', 'og_image']]]);
    }

    public function test_public_profile_returns_only_verified_certifications(): void
    {
        [$user, $coach] = $this->createCoachUser([], ['is_published' => true]);

        Certification::create([
            'coach_id'     => $coach->id,
            'name'         => 'Verified Cert',
            'file_url'     => 'https://cdn.example.com/cert1.pdf',
            'storage_path' => 'certifications/1/cert1.pdf',
            'verified_at'  => now(),
        ]);
        Certification::create([
            'coach_id'     => $coach->id,
            'name'         => 'Unverified Cert',
            'file_url'     => 'https://cdn.example.com/cert2.pdf',
            'storage_path' => 'certifications/1/cert2.pdf',
            'verified_at'  => null,
        ]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertOk()
            ->assertJsonCount(1, 'data.certifications')
            ->assertJsonPath('data.certifications.0.name', 'Verified Cert');
    }

    public function test_public_profile_returns_next_5_availability_slots(): void
    {
        [$user, $coach] = $this->createCoachUser([], ['is_published' => true]);

        for ($i = 0; $i < 7; $i++) {
            $coach->availabilities()->create([
                'day_of_week'      => $i % 7,
                'start_time'       => '09:00',
                'end_time'         => '10:00',
                'duration_minutes' => 60,
                'is_recurring'     => true,
            ]);
        }

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertOk()
            ->assertJsonCount(5, 'data.next_slots');
    }

    public function test_unpublished_coach_returns_404_on_public_profile(): void
    {
        [$user, $coach] = $this->createCoachUser([], ['is_published' => false]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertNotFound();
    }

    public function test_public_profile_does_not_expose_private_fields(): void
    {
        [$user, $coach] = $this->createCoachUser([], [
            'is_published' => true,
            'p_iva'        => '12345678901',
            'pec'          => 'coach@pec.it',
        ]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('p_iva', $data);
        $this->assertArrayNotHasKey('pec', $data);
        $this->assertArrayNotHasKey('stripe_connect_id', $data);
    }
}
