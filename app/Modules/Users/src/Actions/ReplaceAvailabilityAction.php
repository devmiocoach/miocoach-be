<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Coach;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReplaceAvailabilityAction
{
    public function handle(Coach $coach, array $slots): Collection
    {
        return DB::transaction(function () use ($coach, $slots) {
            $coach->availabilities()->delete();

            $records = array_map(fn ($slot) => array_merge($slot, ['coach_id' => $coach->id]), $slots);
            $coach->availabilities()->createMany($records);

            return $coach->availabilities()->get();
        });
    }
}
