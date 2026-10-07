<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Coach;

class PublishCoachProfileAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Coach $coach): array
    {
        $missing = [];

        if (empty($coach->bio)) {
            $missing[] = 'bio';
        }
        if (empty($coach->specializations)) {
            $missing[] = 'specializations';
        }
        if (empty($coach->city)) {
            $missing[] = 'city';
        }
        if (is_null($coach->price_per_session)) {
            $missing[] = 'price_per_session';
        }

        if (! empty($missing)) {
            return ['ok' => false, 'missing' => $missing];
        }

        $coach->update(['is_published' => true]);
        $this->audit->log('PROFILE_PUBLISHED', $coach->user_id);

        return ['ok' => true, 'coach' => $coach->fresh()];
    }
}
