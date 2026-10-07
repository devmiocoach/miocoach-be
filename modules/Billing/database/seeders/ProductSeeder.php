<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Enums\BillingScheme;
use Modules\Billing\Enums\Currency;
use Modules\Billing\Models\PaymentProvider;
use Modules\Billing\Models\Product;

class ProductSeeder extends Seeder
{
    private ?int $stripeProviderId = null;

    public function run(): void
    {
        $this->stripeProviderId = PaymentProvider::where('slug', 'stripe')->value('id');

        $this->createStarterProduct();
        $this->createProProduct();
        $this->createBusinessProduct();
    }

    private function createStarterProduct(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'starter'],
            [
                'sku' => 'starter',
                'name' => 'Starter',
                'description' => 'Inizia a gestire i tuoi clienti in modo professionale.',
                'display_order' => 1,
                'is_visible' => true,
                'is_highlighted' => false,
                'is_active' => true,
                'features' => [
                    'Fino a 25 clienti',
                    'Gestionale clienti completo',
                    'Piani di allenamento e alimentari',
                    'Prenotazioni e Agenda',
                    'App Mobile (Coach e Clienti)',
                    'Pagamenti online (Stripe, PayPal)',
                ],
                'metadata' => [
                    'tagline' => 'Per coach agli inizi',
                    'cta_label' => 'Prova gratis per 14 giorni',
                ],
            ]
        );

        foreach ([
            [
                'payment_provider_id' => $this->stripeProviderId,
                'provider_price_id' => 'price_starter_monthly',
                'currency' => Currency::default(),
                'amount' => 1990,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'payment_provider_id' => $this->stripeProviderId,
                'provider_price_id' => 'price_starter_yearly',
                'currency' => Currency::default(),
                'amount' => 19900,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
                'metadata' => [
                    'badge' => '- 2 mesi',
                    'original_price' => '23880',
                ],
            ],
        ] as $price) {
            $product->prices()->updateOrCreate(
                ['provider_price_id' => $price['provider_price_id']],
                $price
            );
        }
    }

    private function createProProduct(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'pro'],
            [
                'sku' => 'pro',
                'name' => 'Pro',
                'description' => 'Fai crescere il tuo business e trova nuovi clienti.',
                'display_order' => 2,
                'is_visible' => true,
                'is_highlighted' => true,
                'is_active' => true,
                'features' => [
                    'Tutto del piano Starter',
                    'Fino a 100 clienti',
                    'Profilo pubblico su "Trova il tuo Coach"',
                    'Recensioni verificate sul profilo',
                    'Ricevi prenotazioni da nuovi clienti',
                    'Fatturazione e abbonamenti automatici',
                ],
                'metadata' => [
                    'badge' => 'Miglior Scelta',
                    'tagline' => 'Per coach avviati',
                    'cta_label' => 'Prova gratis per 14 giorni',
                ],
            ]
        );

        foreach ([
            [
                'payment_provider_id' => $this->stripeProviderId,
                'provider_price_id' => 'price_pro_monthly',
                'currency' => Currency::default(),
                'amount' => 4990,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'payment_provider_id' => $this->stripeProviderId,
                'provider_price_id' => 'price_pro_yearly',
                'currency' => Currency::default(),
                'amount' => 49900,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
                'metadata' => [
                    'badge' => '- 2 mesi',
                    'original_price' => '59880',
                ],
            ],
        ] as $price) {
            $product->prices()->updateOrCreate(
                ['provider_price_id' => $price['provider_price_id']],
                $price
            );
        }
    }

    private function createBusinessProduct(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'business'],
            [
                'sku' => 'business',
                'name' => 'Business',
                'description' => 'Scala il tuo business senza alcun limite.',
                'display_order' => 3,
                'is_visible' => true,
                'is_highlighted' => false,
                'is_active' => true,
                'features' => [
                    'Tutto del piano Pro',
                    'Clienti ILLIMITATI',
                    'Visibilità Premium nelle ricerche',
                    'Assistenza prioritaria dedicata',
                    'Analitiche avanzate del business',
                ],
                'metadata' => [
                    'tagline' => 'Per top coach e studi',
                    'cta_label' => 'Prova gratis per 14 giorni',
                ],
            ]
        );

        foreach ([
            [
                'payment_provider_id' => $this->stripeProviderId,
                'provider_price_id' => 'price_business_monthly',
                'currency' => Currency::default(),
                'amount' => 8990,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'payment_provider_id' => $this->stripeProviderId,
                'provider_price_id' => 'price_business_yearly',
                'currency' => Currency::default(),
                'amount' => 89900,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
                'metadata' => [
                    'badge' => '- 2 mesi',
                    'original_price' => '107880',
                ],
            ],
        ] as $price) {
            $product->prices()->updateOrCreate(
                ['provider_price_id' => $price['provider_price_id']],
                $price
            );
        }
    }
}
