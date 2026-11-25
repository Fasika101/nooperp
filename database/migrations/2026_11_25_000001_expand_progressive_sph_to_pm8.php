<?php

use App\Models\OpticalRxDiopterValue;
use App\Support\OpticalRxConfig;
use App\Support\OpticalRxOptions;
use Illuminate\Database\Migrations\Migration;

/**
 * Progressive SPH was seeded at ±3.00. Widen the POS dropdown to ±8.00 and
 * leave pricing to OpticalRxConfig (high-SPH flat price when |SPH| > 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        OpticalRxConfig::ensureSeeded();

        foreach (OpticalRxOptions::pricedSphereKeys('progressive') as $value) {
            OpticalRxDiopterValue::query()->updateOrCreate(
                [
                    'group' => OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH,
                    'value' => $value,
                ],
                [
                    'price' => 0,
                    'sort_order' => OpticalRxConfig::sortOrderForValue($value),
                    'is_default' => true,
                ],
            );
        }

        OpticalRxConfig::clearCache();
    }

    public function down(): void
    {
        OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH)
            ->where(function ($q) {
                $q->whereRaw('ABS(CAST(value AS DECIMAL(8,2))) > ?', [3.0]);
            })
            ->where('is_default', true)
            ->delete();

        OpticalRxConfig::clearCache();
    }
};
