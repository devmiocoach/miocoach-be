<?php

namespace App\Modules\Users\Tests\Unit;

use App\Modules\Users\Models\CoachPackage;
use PHPUnit\Framework\TestCase;

class CoachPackageSavingsTest extends TestCase
{
    private function makePackage(int $sessions, int $priceCents): CoachPackage
    {
        $pkg = new CoachPackage;
        $pkg->sessions_count = $sessions;
        $pkg->price_cents = $priceCents;

        return $pkg;
    }

    public function test_savings_are_zero_when_no_single_price(): void
    {
        $pkg = $this->makePackage(5, 22000);

        $this->assertSame(0, $pkg->getSavingsCentsAttribute(null));
    }

    public function test_savings_are_zero_when_package_is_not_discounted(): void
    {
        // 5 sessions × 5000 = 25000, package costs 25000 → no savings
        $pkg = $this->makePackage(5, 25000);

        $this->assertSame(0, $pkg->getSavingsCentsAttribute(5000));
    }

    public function test_savings_calculated_correctly(): void
    {
        // 5 sessions × 5000 = 25000, package costs 22000 → saves 3000 (€30)
        $pkg = $this->makePackage(5, 22000);

        $this->assertSame(3000, $pkg->getSavingsCentsAttribute(5000));
    }

    public function test_savings_for_10_session_package(): void
    {
        // 10 × 6500 = 65000, package 58500 → saves 6500
        $pkg = $this->makePackage(10, 58500);

        $this->assertSame(6500, $pkg->getSavingsCentsAttribute(6500));
    }

    public function test_price_in_euros_conversion(): void
    {
        $pkg = $this->makePackage(5, 22000);

        $this->assertEqualsWithDelta(220.0, $pkg->getPriceInEurosAttribute(), 0.01);
    }

    public function test_price_in_euros_for_fractional_amount(): void
    {
        $pkg = $this->makePackage(5, 22050);

        $this->assertEqualsWithDelta(220.5, $pkg->getPriceInEurosAttribute(), 0.01);
    }

    public function test_savings_cannot_be_negative(): void
    {
        // Package costs more than full price (hypothetical) → clamps to 0
        $pkg = $this->makePackage(5, 30000);

        $this->assertSame(0, $pkg->getSavingsCentsAttribute(5000));
    }
}
