<?php

namespace Modules\Bookings\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Filament\Resources\Bookings\Pages\CreateBooking;
use Modules\Bookings\Filament\Resources\Bookings\Pages\EditBooking;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;
use Tests\TestCase;

class BookingFilamentResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Coach $coach;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole(Role::ADMIN);
        $this->coach = Coach::factory()->create();
        $this->client = Client::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
    }

    public function test_filament_create_uses_booking_service(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'client_id' => $this->client->id,
            'service_type' => BookingServiceType::PersonalTraining->value,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $service = Mockery::mock(BookingService::class);
        $service->shouldReceive('createForCoach')
            ->once()
            ->withArgs(fn (Coach $coach, Client $client, array $data) => $coach->is($this->coach)
                && $client->is($this->client)
                && $data['service_type'] === BookingServiceType::PersonalTraining->value)
            ->andReturn($booking);
        $this->app->instance(BookingService::class, $service);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'coach_id' => $this->coach->id,
                'client_id' => $this->client->id,
                'service_type' => BookingServiceType::PersonalTraining->value,
                'starts_at' => '2026-09-01 10:00:00',
                'ends_at' => '2026-09-01 11:00:00',
                'status' => BookingStatus::Confirmed->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_filament_edit_reschedule_uses_booking_service(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'client_id' => $this->client->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-09-01 11:00:00',
        ]);

        $service = Mockery::mock(BookingService::class);
        $service->shouldReceive('reschedule')
            ->once()
            ->withArgs(fn (Booking $record, array $data) => $record->is($booking)
                && $data['starts_at'] === '2026-09-02 10:00:00'
                && $data['ends_at'] === '2026-09-02 11:00:00');
        $this->app->instance(BookingService::class, $service);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm([
                'coach_id' => $this->coach->id,
                'client_id' => $this->client->id,
                'service_type' => BookingServiceType::PersonalTraining->value,
                'starts_at' => '2026-09-02 10:00:00',
                'ends_at' => '2026-09-02 11:00:00',
                'status' => BookingStatus::Confirmed->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_filament_delete_uses_booking_service(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'client_id' => $this->client->id,
        ]);

        $service = Mockery::mock(BookingService::class);
        $service->shouldReceive('cancel')
            ->once()
            ->withArgs(fn (Booking $record, string $reason, string $cancelledBy) => $record->is($booking)
                && $reason === 'Cancelled from Filament.'
                && $cancelledBy === BookingCancelledBy::Coach->value);
        $this->app->instance(BookingService::class, $service);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->callAction('delete')
            ->assertNotified();
    }
}
