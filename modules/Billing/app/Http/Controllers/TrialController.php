<?php

namespace Modules\Billing\Http\Controllers;

use App\Helpers\Toast;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Models\Customer;
use Modules\Billing\Models\Price;
use Modules\Billing\Models\Subscription;

class TrialController
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'price_id' => ['required', 'exists:prices,id,is_active,1'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->isSubscriber()) {
            Toast::default(__('Hai già un abbonamento attivo.'));

            return redirect()->route('dashboard');
        }

        $price = Price::findOrFail($validated['price_id']);

        $customer = Customer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email' => $user->email,
                'name' => $user->name,
            ],
        );

        $now = now();
        $trialEnd = $now->copy()->addDays(14);

        $subscription = DB::transaction(function () use ($customer, $price, $now, $trialEnd) {
            return Subscription::create([
                'customer_id' => $customer->id,
                'price_id' => $price->id,
                'status' => SubscriptionStatus::Active,
                'trial_starts_at' => $now,
                'trial_ends_at' => $trialEnd,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $trialEnd,
            ]);
        });

        SubscriptionCreated::dispatch($subscription);

        Toast::default(__('Prova gratuita attivata! Goditi 14 giorni di accesso completo.'));

        return redirect()->route('dashboard');
    }
}
