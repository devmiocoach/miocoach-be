<?php

namespace App\Modules\Users\Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientNote;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Seeder;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;

class CoachTestClientsSeeder extends Seeder
{
    public function run(): void
    {
        $coachUser = User::where('email', 'testcoach@gmail.com')->firstOrFail();
        $coach = $coachUser->coach;

        $clients = $this->createClients($coach);
        $this->createBookings($coach, $clients);
        $this->createNotes($coach, $clients);
    }

    private function createClients(Coach $coach): array
    {
        $clientsData = [
            [
                'user' => ['name' => 'Marco Bianchi', 'email' => 'marco.bianchi@example.com'],
                'client' => [
                    'goals' => ['Perdita peso', 'Tonificazione'],
                    'fitness_level' => 'intermediate',
                    'tags' => ['vip', 'mattina'],
                    'gender' => 'male',
                    'birth_date' => '1990-03-15',
                    'height_cm' => 178,
                    'weight_kg' => 82.5,
                    'subscription_expires_at' => now()->addDays(45),
                    'subscription_sessions_remaining' => 8,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subMonths(6)],
            ],
            [
                'user' => ['name' => 'Giulia Rossi', 'email' => 'giulia.rossi@example.com'],
                'client' => [
                    'goals' => ['Aumento massa muscolare', 'Forza'],
                    'fitness_level' => 'advanced',
                    'tags' => ['vip', 'sera', 'competizione'],
                    'gender' => 'female',
                    'birth_date' => '1995-07-22',
                    'height_cm' => 165,
                    'weight_kg' => 58.0,
                    'subscription_expires_at' => now()->addDays(90),
                    'subscription_sessions_remaining' => 12,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subMonths(4)],
            ],
            [
                'user' => ['name' => 'Alessandro Verdi', 'email' => 'ale.verdi@example.com'],
                'client' => [
                    'goals' => ['Riabilitazione ginocchio', 'Mobilità'],
                    'fitness_level' => 'beginner',
                    'tags' => ['riabilitazione', 'mattina'],
                    'gender' => 'male',
                    'birth_date' => '1985-11-08',
                    'height_cm' => 182,
                    'weight_kg' => 90.3,
                    'subscription_expires_at' => now()->addDays(5),
                    'subscription_sessions_remaining' => 2,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subMonths(2)],
            ],
            [
                'user' => ['name' => 'Francesca Neri', 'email' => 'francesca.neri@example.com'],
                'client' => [
                    'goals' => ['Benessere generale', 'Flessibilità'],
                    'fitness_level' => 'beginner',
                    'tags' => ['sera', 'online'],
                    'gender' => 'female',
                    'birth_date' => '1988-01-30',
                    'height_cm' => 170,
                    'weight_kg' => 65.0,
                    'subscription_expires_at' => now()->subDays(3),
                    'subscription_sessions_remaining' => 0,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subMonths(3)],
            ],
            [
                'user' => ['name' => 'Luca Moretti', 'email' => 'luca.moretti@example.com'],
                'client' => [
                    'goals' => ['Preparazione maratona'],
                    'fitness_level' => 'advanced',
                    'tags' => ['running', 'mattina', 'vip'],
                    'gender' => 'male',
                    'birth_date' => '1992-06-12',
                    'height_cm' => 175,
                    'weight_kg' => 70.0,
                    'subscription_expires_at' => now()->addDays(60),
                    'subscription_sessions_remaining' => 6,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subMonths(8)],
            ],
            [
                'user' => ['name' => 'Sara Colombo', 'email' => 'sara.colombo@example.com'],
                'client' => [
                    'goals' => ['Dimagrimento', 'Tonificazione glutei'],
                    'fitness_level' => 'intermediate',
                    'tags' => ['sera'],
                    'gender' => 'female',
                    'birth_date' => '1997-09-05',
                    'height_cm' => 160,
                    'weight_kg' => 72.0,
                    'subscription_expires_at' => now()->addDays(20),
                    'subscription_sessions_remaining' => 4,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subMonths(1)],
            ],
            [
                'user' => ['name' => 'Roberto Esposito', 'email' => 'roberto.esposito@example.com'],
                'client' => [
                    'goals' => ['Bodybuilding', 'Ipertrofia'],
                    'fitness_level' => 'advanced',
                    'tags' => ['bodybuilding', 'mattina'],
                    'gender' => 'male',
                    'birth_date' => '1993-04-18',
                    'height_cm' => 180,
                    'weight_kg' => 95.0,
                    'subscription_expires_at' => now()->addDays(30),
                    'subscription_sessions_remaining' => 10,
                ],
                'pivot' => ['status' => 'suspended', 'started_at' => now()->subMonths(5)],
            ],
            [
                'user' => ['name' => 'Elena Ricci', 'email' => 'elena.ricci@example.com'],
                'client' => [
                    'goals' => ['Post-gravidanza', 'Core stability'],
                    'fitness_level' => 'beginner',
                    'tags' => ['post-parto', 'mattina', 'online'],
                    'gender' => 'female',
                    'birth_date' => '1991-12-25',
                    'height_cm' => 168,
                    'weight_kg' => 73.5,
                    'subscription_expires_at' => now()->addDays(15),
                    'subscription_sessions_remaining' => 3,
                ],
                'pivot' => ['status' => 'active', 'started_at' => now()->subWeeks(3)],
            ],
        ];

        $clients = [];

        foreach ($clientsData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                array_merge($data['user'], [
                    'type' => UserType::ATLETA,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]),
            );

            $client = Client::firstOrCreate(
                ['user_id' => $user->id],
                $data['client'],
            );

            if (! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
                $coach->athletes()->attach($client->id, $data['pivot']);
            }

            $clients[] = $client;
        }

        return $clients;
    }

    private function createBookings(Coach $coach, array $clients): void
    {
        $serviceTypes = BookingServiceType::cases();

        // Past completed sessions (last 2 weeks)
        foreach (array_slice($clients, 0, 5) as $i => $client) {
            for ($d = 1; $d <= 3; $d++) {
                Booking::firstOrCreate(
                    [
                        'coach_id' => $coach->id,
                        'client_id' => $client->id,
                        'starts_at' => now()->subDays($d * 3 + $i)->setTime(9 + $i, 0),
                    ],
                    [
                        'ends_at' => now()->subDays($d * 3 + $i)->setTime(10 + $i, 0),
                        'duration_minutes' => 60,
                        'status' => BookingStatus::Completed,
                        'service_type' => $serviceTypes[$i % count($serviceTypes)],
                        'price' => fake()->randomElement([40.0, 50.0, 60.0, 75.0]),
                        'source' => 'manual',
                    ],
                );
            }
        }

        // Upcoming confirmed sessions (this week + next)
        $upcomingSlots = [
            ['day' => 0, 'hour' => 9, 'client_idx' => 0],
            ['day' => 0, 'hour' => 11, 'client_idx' => 1],
            ['day' => 0, 'hour' => 15, 'client_idx' => 5],
            ['day' => 1, 'hour' => 8, 'client_idx' => 2],
            ['day' => 1, 'hour' => 10, 'client_idx' => 4],
            ['day' => 1, 'hour' => 16, 'client_idx' => 3],
            ['day' => 2, 'hour' => 9, 'client_idx' => 0],
            ['day' => 2, 'hour' => 14, 'client_idx' => 7],
            ['day' => 3, 'hour' => 10, 'client_idx' => 1],
            ['day' => 3, 'hour' => 17, 'client_idx' => 5],
            ['day' => 4, 'hour' => 9, 'client_idx' => 4],
            ['day' => 4, 'hour' => 11, 'client_idx' => 2],
            ['day' => 5, 'hour' => 10, 'client_idx' => 0],
        ];

        $weekStart = now()->startOfWeek();

        foreach ($upcomingSlots as $slot) {
            $startsAt = $weekStart->copy()->addDays($slot['day'])->setTime($slot['hour'], 0);
            if ($startsAt->isPast()) {
                continue;
            }

            $client = $clients[$slot['client_idx']];

            Booking::firstOrCreate(
                [
                    'coach_id' => $coach->id,
                    'client_id' => $client->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'ends_at' => $startsAt->copy()->addHour(),
                    'duration_minutes' => 60,
                    'status' => BookingStatus::Confirmed,
                    'service_type' => $serviceTypes[array_search($client, $clients) % count($serviceTypes)],
                    'price' => fake()->randomElement([40.0, 50.0, 60.0]),
                    'source' => 'manual',
                ],
            );
        }

        // One pending booking
        Booking::firstOrCreate(
            [
                'coach_id' => $coach->id,
                'client_id' => $clients[3]->id,
                'starts_at' => now()->addDays(5)->setTime(14, 0),
            ],
            [
                'ends_at' => now()->addDays(5)->setTime(15, 0),
                'duration_minutes' => 60,
                'status' => BookingStatus::Pending,
                'service_type' => BookingServiceType::OnlineCoaching,
                'price' => 35.0,
                'source' => 'public_web',
            ],
        );
    }

    private function createNotes(Coach $coach, array $clients): void
    {
        $notesData = [
            [0, 'Ottimi progressi sul deadlift, aumentato 10kg in 2 settimane.'],
            [0, 'Riferisce dolore leggero alla spalla destra, monitorare.'],
            [1, 'Preparazione gara ottobre: iniziare fase di cut tra 2 settimane.'],
            [1, 'Squat form migliorata dopo correzione stance.'],
            [2, 'Ginocchio risponde bene agli esercizi isometrici. Prossima settimana provare leg press leggera.'],
            [3, 'Preferisce sessioni online il martedì. Aggiornare disponibilità.'],
            [4, 'Tempo gara 10km migliorato di 3 minuti. Obiettivo sub-45 raggiungibile.'],
            [5, 'Motivazione alta, buona aderenza al piano alimentare.'],
            [7, 'Prima sessione andata bene. Focus su core e pavimento pelvico.'],
        ];

        foreach ($notesData as [$clientIdx, $content]) {
            ClientNote::firstOrCreate(
                [
                    'coach_id' => $coach->id,
                    'client_id' => $clients[$clientIdx]->id,
                    'content' => $content,
                ],
                ['created_at' => now()->subDays(rand(1, 14))],
            );
        }
    }
}
