<?php

namespace App\Support;

use App\Models\Affiliate;

class AffiliateCommission
{
    /**
     * Customer-facing total after affiliate rules are applied.
     */
    public static function customerTotal(float $base, string $type, float $rate): float
    {
        $base = round(max(0, $base), 2);
        if ($rate <= 0 || $base <= 0) {
            return $base;
        }

        if ($type === Affiliate::COMMISSION_ADD_PERCENT) {
            if ($rate >= 100) {
                return $base;
            }

            return round($base / (1 - ($rate / 100)), 2);
        }

        return $base;
    }

    /**
     * Affiliate commission amount stored on the order.
     *
     * Add %: share of the customer total (gross-up so the shop keeps the base).
     * Deduct %: share of the pre-affiliate base; customer total stays at base.
     */
    public static function commissionAmount(float $base, string $type, float $rate): float
    {
        $base = round(max(0, $base), 2);
        if ($rate <= 0 || $base <= 0) {
            return 0.0;
        }

        if ($type === Affiliate::COMMISSION_ADD_PERCENT) {
            return round(self::customerTotal($base, $type, $rate) - $base, 2);
        }

        return round($base * ($rate / 100), 2);
    }
}
