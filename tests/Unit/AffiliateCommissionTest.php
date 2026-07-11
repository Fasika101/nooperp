<?php

namespace Tests\Unit;

use App\Models\Affiliate;
use App\Support\AffiliateCommission;
use PHPUnit\Framework\TestCase;

class AffiliateCommissionTest extends TestCase
{
    public function test_add_percent_grosses_up_customer_total_and_commission(): void
    {
        $base = 100.0;
        $rate = 15.0;

        $total = AffiliateCommission::customerTotal($base, Affiliate::COMMISSION_ADD_PERCENT, $rate);
        $commission = AffiliateCommission::commissionAmount($base, Affiliate::COMMISSION_ADD_PERCENT, $rate);

        $this->assertSame(117.65, $total);
        $this->assertSame(17.65, $commission);
        $this->assertSame($base, round($total - $commission, 2));
    }

    public function test_add_percent_works_with_different_rates(): void
    {
        $base = 100.0;

        $totalTen = AffiliateCommission::customerTotal($base, Affiliate::COMMISSION_ADD_PERCENT, 10.0);
        $commissionTen = AffiliateCommission::commissionAmount($base, Affiliate::COMMISSION_ADD_PERCENT, 10.0);

        $this->assertSame(111.11, $totalTen);
        $this->assertSame(11.11, $commissionTen);
        $this->assertSame($base, round($totalTen - $commissionTen, 2));
    }

    public function test_deduct_percent_leaves_customer_total_at_base(): void
    {
        $base = 100.0;
        $rate = 15.0;

        $total = AffiliateCommission::customerTotal($base, Affiliate::COMMISSION_DEDUCT_PERCENT, $rate);
        $commission = AffiliateCommission::commissionAmount($base, Affiliate::COMMISSION_DEDUCT_PERCENT, $rate);

        $this->assertSame(100.0, $total);
        $this->assertSame(15.0, $commission);
    }

    public function test_zero_rate_returns_base_with_no_commission(): void
    {
        $this->assertSame(250.0, AffiliateCommission::customerTotal(250.0, Affiliate::COMMISSION_ADD_PERCENT, 0.0));
        $this->assertSame(0.0, AffiliateCommission::commissionAmount(250.0, Affiliate::COMMISSION_ADD_PERCENT, 0.0));
    }
}
