<?php

namespace Modules\CoachCommerce\Providers;

use App\Modules\Users\Models\Coach;
use App\Providers\ModuleServiceProvider;
use Modules\CoachCommerce\Models\SubscriptionPlan;

class CoachCommerceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'CoachCommerce';

    protected string $nameLower = 'coachcommerce';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->registerCoachRelations();
    }

    private function registerCoachRelations(): void
    {
        Coach::resolveRelationUsing('subscriptionPlans', fn (Coach $coach) => $coach
            ->hasMany(SubscriptionPlan::class, 'coach_id')
        );
    }
}
