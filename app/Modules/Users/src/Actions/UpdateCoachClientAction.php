<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Facades\DB;

class UpdateCoachClientAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Client $client, Coach $coach, array $data): Client
    {
        DB::transaction(function () use ($client, $coach, $data) {
            if (isset($data['first_name']) || isset($data['last_name'])) {
                $nameParts = explode(' ', $client->user->name ?? '', 2);
                $firstName = $data['first_name'] ?? ($nameParts[0] ?? '');
                $lastName  = $data['last_name']  ?? ($nameParts[1] ?? '');
                $client->user->update(['name' => trim("{$firstName} {$lastName}")]);
            }

            $clientFields = array_intersect_key($data, array_flip([
                'phone', 'avatar_url', 'tags', 'status',
                'birth_date', 'gender', 'height_cm', 'weight_kg', 'goals',
            ]));

            $client->update($clientFields);

            $this->audit->log('CLIENT_UPDATED', $coach->user_id, ['client_id' => $client->id]);
        });

        return $client->fresh(['user']);
    }
}
