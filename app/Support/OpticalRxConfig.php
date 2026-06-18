<?php

namespace App\Support;

use App\Models\OpticalRxDiopterValue;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OpticalRxConfig
{
    public const CACHE_KEY = 'optical_rx_config_v1';

    /** Setting keys for single-vision compound tier flat prices */
    public const COMPOUND_SV_TIER1_SETTING = 'optical_sv_compound_tier1_price';
    public const COMPOUND_SV_TIER2_SETTING = 'optical_sv_compound_tier2_price';

    /**
     * Returned from prescriptionAddOn() when single-vision compound values are
     * beyond both tiers — the cashier must enter a custom price in the POS.
     */
    public const COMPOUND_CUSTOM_SENTINEL = -1.0;

    public static function sphereGroupForVision(string $vision): string
    {
        return $vision === 'progressive'
            ? OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH
            : OpticalRxDiopterValue::GROUP_SINGLE_SPH;
    }

    public static function cylinderGroupForVision(string $vision): string
    {
        return $vision === 'progressive'
            ? OpticalRxDiopterValue::GROUP_PROGRESSIVE_CYL
            : OpticalRxDiopterValue::GROUP_SINGLE_CYL;
    }

    public static function ensureSeeded(): void
    {
        if (OpticalRxDiopterValue::query()->exists()) {
            return;
        }

        self::seedDefaults();
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, string>> group => [value => label]
     */
    public static function allOptionsCached(): array
    {
        self::ensureSeeded();

        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $result = [];
            foreach (OpticalRxDiopterValue::groups() as $group) {
                $result[$group] = self::buildOptionsForGroup($group);
            }

            return $result;
        });
    }

    /**
     * @return array<string, string>
     */
    public static function sphereOptions(string $vision): array
    {
        $all = self::allOptionsCached();

        return $all[self::sphereGroupForVision($vision)] ?? [OpticalRxOptions::UNKNOWN => '—'];
    }

    /**
     * @return array<string, string>
     */
    public static function cylinderOptions(string $vision): array
    {
        $all = self::allOptionsCached();

        return $all[self::cylinderGroupForVision($vision)] ?? [OpticalRxOptions::UNKNOWN => '—'];
    }

    public static function priceForGroupValue(string $group, ?string $value): float
    {
        if ($value === null || $value === '' || $value === OpticalRxOptions::UNKNOWN) {
            return 0.0;
        }

        self::ensureSeeded();

        $row = OpticalRxDiopterValue::query()
            ->where('group', $group)
            ->where('value', $value)
            ->first();

        return $row ? (float) $row->price : 0.0;
    }

    public static function eyeAddOn(string $vision, ?string $sph, ?string $cyl): float
    {
        return self::priceForGroupValue(self::sphereGroupForVision($vision), $sph)
            + self::priceForGroupValue(self::cylinderGroupForVision($vision), $cyl);
    }

    public static function getCompoundSvTier1Price(): float
    {
        return (float) Setting::get(self::COMPOUND_SV_TIER1_SETTING, 0);
    }

    public static function getCompoundSvTier2Price(): float
    {
        return (float) Setting::get(self::COMPOUND_SV_TIER2_SETTING, 0);
    }

    private static function isBlank(?string $val): bool
    {
        if ($val === null || $val === '' || $val === OpticalRxOptions::UNKNOWN) {
            return true;
        }
        // Plano (0.00) = no refractive power = treat as absent
        return is_numeric($val) && (float) $val === 0.0;
    }

    private static function absFloat(?string $val): float
    {
        return self::isBlank($val) ? 0.0 : abs((float) $val);
    }

    /**
     * Returns the diopter add-on price for a prescription.
     *
     * For progressive vision: sum of both eyes (unchanged).
     *
     * For single vision:
     *   - SPH only  → higher of both eyes' SPH prices (one pair price)
     *   - CYL only  → higher of both eyes' CYL prices (one pair price)
     *   - Compound  → flat tier price based on worst-eye absolute values:
     *       Tier 1 (mild):   effectiveSph ≤ 4.00 AND effectiveCyl ≤ 2.00
     *       Tier 2 (strong): effectiveSph > 4.00 OR effectiveCyl > 2.00 (up to 9/4)
     *       Beyond tiers:    returns COMPOUND_CUSTOM_SENTINEL (-1.0) — cashier enters price manually
     */
    public static function prescriptionAddOn(
        string $vision,
        ?string $odSph,
        ?string $odCyl,
        ?string $osSph,
        ?string $osCyl,
    ): float {
        // Progressive: keep existing per-eye additive logic (unchanged)
        if ($vision !== 'single') {
            return round(
                self::eyeAddOn($vision, $odSph, $odCyl)
                + self::eyeAddOn($vision, $osSph, $osCyl),
                2,
            );
        }

        // ── Single vision ────────────────────────────────────────────────────
        $hasSph = ! self::isBlank($odSph) || ! self::isBlank($osSph);
        $hasCyl = ! self::isBlank($odCyl) || ! self::isBlank($osCyl);

        if (! $hasSph && ! $hasCyl) {
            return 0.0;
        }

        $sphGroup = self::sphereGroupForVision('single');
        $cylGroup = self::cylinderGroupForVision('single');

        if ($hasSph && ! $hasCyl) {
            // SPH only — one price for the pair (higher value wins)
            return round(max(
                self::priceForGroupValue($sphGroup, $odSph),
                self::priceForGroupValue($sphGroup, $osSph),
            ), 2);
        }

        if ($hasCyl && ! $hasSph) {
            // CYL only — one price for the pair (higher value wins)
            return round(max(
                self::priceForGroupValue($cylGroup, $odCyl),
                self::priceForGroupValue($cylGroup, $osCyl),
            ), 2);
        }

        // Compound (both SPH and CYL present) — tier-based flat price
        $effectiveSph = max(self::absFloat($odSph), self::absFloat($osSph));
        $effectiveCyl = max(self::absFloat($odCyl), self::absFloat($osCyl));

        // Beyond both tiers — custom price required in POS
        if ($effectiveSph > 9.00 || $effectiveCyl > 4.00) {
            return self::COMPOUND_CUSTOM_SENTINEL;
        }

        // Tier 2: either value exceeds Tier 1 boundary
        if ($effectiveSph > 4.00 || $effectiveCyl > 2.00) {
            return round(self::getCompoundSvTier2Price(), 2);
        }

        // Tier 1: both values within mild range (0.25–4.00 / 0.25–2.00)
        return round(self::getCompoundSvTier1Price(), 2);
    }

    /**
     * @return list<array{value: string, price: string, is_default: bool}>
     */
    public static function rowsForForm(string $group): array
    {
        self::ensureSeeded();

        return OpticalRxDiopterValue::query()
            ->where('group', $group)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->map(fn (OpticalRxDiopterValue $row): array => [
                'value' => $row->value,
                'price' => (string) $row->price,
                'is_default' => $row->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<array{value?: string, price?: mixed, is_default?: bool}>>  $groups
     */
    public static function saveFromForm(array $groups): void
    {
        foreach (OpticalRxDiopterValue::groups() as $group) {
            $rows = $groups[$group] ?? [];
            $submittedValues = [];
            $seen = [];

            foreach ($rows as $index => $row) {
                $value = self::normalizeDiopterValue($row['value'] ?? '');
                if ($value === null) {
                    throw ValidationException::withMessages([
                        "{$group}.{$index}.value" => __('Enter a valid diopter value (e.g. -6.00 or 7.25).'),
                    ]);
                }

                if (in_array($value, $seen, true)) {
                    throw ValidationException::withMessages([
                        "{$group}.{$index}.value" => __('Duplicate value :value in this group.', ['value' => OpticalRxOptions::formatLabel($value)]),
                    ]);
                }

                $seen[] = $value;
                $submittedValues[] = $value;
                $existing = OpticalRxDiopterValue::query()
                    ->where('group', $group)
                    ->where('value', $value)
                    ->first();

                if ($existing?->is_default) {
                    $value = $existing->value;
                }

                $isDefault = $existing?->is_default ?? (bool) ($row['is_default'] ?? false);
                $price = is_numeric($row['price'] ?? null) ? round((float) $row['price'], 2) : 0.0;

                OpticalRxDiopterValue::query()->updateOrCreate(
                    ['group' => $group, 'value' => $value],
                    [
                        'price' => $price,
                        'is_default' => $isDefault,
                        'sort_order' => self::sortOrderForValue($value),
                    ],
                );
            }

            if ($submittedValues !== []) {
                OpticalRxDiopterValue::query()
                    ->where('group', $group)
                    ->where('is_default', false)
                    ->whereNotIn('value', $submittedValues)
                    ->delete();
            }
        }

        self::clearCache();
    }

    public static function normalizeDiopterValue(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $raw));
        if (! is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public static function sortOrderForValue(string $value): int
    {
        // Offset keeps sort_order non-negative (works with unsigned DB columns).
        return 10000 + (int) round(((float) $value) * 100);
    }

    /**
     * @return array<string, string>
     */
    protected static function buildOptionsForGroup(string $group): array
    {
        $rows = OpticalRxDiopterValue::query()
            ->where('group', $group)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get();

        if ($rows->isEmpty()) {
            return self::fallbackOptionsForGroup($group);
        }

        $opts = [OpticalRxOptions::UNKNOWN => OpticalRxOptions::formatLabel(OpticalRxOptions::UNKNOWN)];
        foreach ($rows as $row) {
            $opts[$row->value] = OpticalRxOptions::formatLabel($row->value);
        }

        return $opts;
    }

    /**
     * @return array<string, string>
     */
    protected static function fallbackOptionsForGroup(string $group): array
    {
        return match ($group) {
            OpticalRxDiopterValue::GROUP_SINGLE_SPH => OpticalRxOptions::sphereOptions('single'),
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH => OpticalRxOptions::sphereOptions('progressive'),
            OpticalRxDiopterValue::GROUP_SINGLE_CYL,
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_CYL => OpticalRxOptions::cylinderOptions(),
            default => [OpticalRxOptions::UNKNOWN => '—'],
        };
    }

    public static function seedDefaults(): void
    {
        $legacyPrices = self::legacyPricesFromSettings();

        $definitions = [
            OpticalRxDiopterValue::GROUP_SINGLE_SPH => OpticalRxOptions::pricedSphereKeys('single'),
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH => OpticalRxOptions::pricedSphereKeys('progressive'),
            OpticalRxDiopterValue::GROUP_SINGLE_CYL => OpticalRxOptions::pricedCylinderKeys(),
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_CYL => OpticalRxOptions::pricedCylinderKeys(),
        ];

        foreach ($definitions as $group => $values) {
            foreach ($values as $value) {
                $price = $legacyPrices[$group][$value] ?? 0.0;

                OpticalRxDiopterValue::query()->updateOrCreate(
                    ['group' => $group, 'value' => $value],
                    [
                        'price' => $price,
                        'sort_order' => self::sortOrderForValue($value),
                        'is_default' => true,
                    ],
                );
            }
        }

        self::clearCache();
    }

    /**
     * @return array<string, array<string, float>>
     */
    protected static function legacyPricesFromSettings(): array
    {
        $stored = Setting::get(OpticalRxPricing::SETTING_KEY);
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach (OpticalRxDiopterValue::groups() as $group) {
            $result[$group] = [];
            if (! isset($decoded[$group]) || ! is_array($decoded[$group])) {
                continue;
            }
            foreach ($decoded[$group] as $value => $price) {
                if (is_numeric($price)) {
                    $result[$group][(string) $value] = round((float) $price, 2);
                }
            }
        }

        return $result;
    }
}
