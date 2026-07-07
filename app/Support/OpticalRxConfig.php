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

    /** Setting keys for progressive lens add-on prices */
    public const PROGRESSIVE_ADD_TIER1_SETTING = 'optical_pg_add_tier1_price';
    public const PROGRESSIVE_ADD_TIER2_SETTING = 'optical_pg_add_tier2_price';
    public const PROGRESSIVE_NEGATIVE_SPH_SETTING = 'optical_pg_negative_sph_price';
    public const PROGRESSIVE_CYL_SETTING = 'optical_pg_cyl_price';

    /** ADD boundary (diopters): up to this value uses tier 1, above uses tier 2 */
    public const PROGRESSIVE_ADD_TIER1_MAX = 3.00;

    /** +SPH boundary for flat plus-progressive pricing paired with ADD */
    public const PROGRESSIVE_PLUS_SPH_TIER1_MAX = 3.00;

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
        if (! OpticalRxDiopterValue::query()->exists()) {
            self::seedDefaults();
        }

        self::ensureProgressiveAddSeeded();
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

    /**
     * @return array<string, string>
     */
    public static function addOptions(): array
    {
        $all = self::allOptionsCached();

        return $all[OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD] ?? [OpticalRxOptions::UNKNOWN => '—'];
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

    public static function getProgressiveAddTier1Price(): float
    {
        return (float) Setting::get(self::PROGRESSIVE_ADD_TIER1_SETTING, 3600);
    }

    public static function getProgressiveAddTier2Price(): float
    {
        return (float) Setting::get(self::PROGRESSIVE_ADD_TIER2_SETTING, 4500);
    }

    public static function getProgressiveNegativeSphPrice(): float
    {
        return (float) Setting::get(self::PROGRESSIVE_NEGATIVE_SPH_SETTING, 2900);
    }

    public static function getProgressiveCylPrice(): float
    {
        return (float) Setting::get(self::PROGRESSIVE_CYL_SETTING, 2900);
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

    private static function signedFloat(?string $val): ?float
    {
        if (self::isBlank($val)) {
            return null;
        }

        return (float) $val;
    }

    private static function hasNegativeSphere(?string $odSph, ?string $osSph): bool
    {
        foreach ([$odSph, $osSph] as $sph) {
            $signed = self::signedFloat($sph);
            if ($signed !== null && $signed < 0) {
                return true;
            }
        }

        return false;
    }

    private static function hasCylinder(?string $odCyl, ?string $osCyl): bool
    {
        return ! self::isBlank($odCyl) || ! self::isBlank($osCyl);
    }

    private static function hasAddPower(?string $odAdd, ?string $osAdd): bool
    {
        return ! self::isBlank($odAdd) || ! self::isBlank($osAdd);
    }

    /**
     * Worst-eye ADD used for progressive tier lookup (higher ADD wins).
     */
    private static function effectiveAddPower(?string $odAdd, ?string $osAdd): float
    {
        return max(self::absFloat($odAdd), self::absFloat($osAdd));
    }

    /**
     * Highest non-negative SPH across both eyes (plano counts as 0).
     */
    private static function effectivePlusSphere(?string $odSph, ?string $osSph): float
    {
        $max = 0.0;

        foreach ([$odSph, $osSph] as $sph) {
            $signed = self::signedFloat($sph);
            if ($signed !== null && $signed >= 0) {
                $max = max($max, $signed);
            }
        }

        return $max;
    }

    private static function progressiveAddTierPrice(float $effectiveAdd): float
    {
        return $effectiveAdd > self::PROGRESSIVE_ADD_TIER1_MAX
            ? self::getProgressiveAddTier2Price()
            : self::getProgressiveAddTier1Price();
    }

    /**
     * Flat plus-progressive price when +SPH/plano is paired with ADD (not stacked).
     */
    private static function progressivePlusFlatPrice(float $effectivePlusSph, float $effectiveAdd): float
    {
        if ($effectivePlusSph > self::PROGRESSIVE_PLUS_SPH_TIER1_MAX
            && $effectiveAdd > self::PROGRESSIVE_ADD_TIER1_MAX) {
            return self::getProgressiveAddTier2Price();
        }

        if ($effectiveAdd > self::PROGRESSIVE_ADD_TIER1_MAX) {
            return self::getProgressiveAddTier2Price();
        }

        return self::getProgressiveAddTier1Price();
    }

    /**
     * Progressive lens diopter add-on (pair price).
     *
     *   ADD only (plano/+SPH 0–+3)           → tier 1 (default 3600)
     *   ADD > +3 (or +SPH > +3 with ADD > +3) → tier 2 (default 4500)
     *   −SPH alone                            → 2900
     *   CYL alone                             → 2900
     *   (−SPH or CYL) + ADD                   → 2900 + ADD tier price (stacked)
     *   +SPH + ADD (no −SPH/CYL)              → flat ADD tier price (not stacked)
     */
    private static function progressivePrescriptionAddOn(
        ?string $odSph,
        ?string $odCyl,
        ?string $osSph,
        ?string $osCyl,
        ?string $odAdd,
        ?string $osAdd,
    ): float {
        $hasAdd = self::hasAddPower($odAdd, $osAdd);
        $hasNegSph = self::hasNegativeSphere($odSph, $osSph);
        $hasCyl = self::hasCylinder($odCyl, $osCyl);
        $hasSph = ! self::isBlank($odSph) || ! self::isBlank($osSph);

        if (! $hasAdd && ! $hasSph && ! $hasCyl) {
            return 0.0;
        }

        $effectiveAdd = self::effectiveAddPower($odAdd, $osAdd);
        $addTierPrice = self::progressiveAddTierPrice($effectiveAdd);

        if ($hasAdd && ($hasNegSph || $hasCyl)) {
            $base = $hasCyl
                ? self::getProgressiveCylPrice()
                : self::getProgressiveNegativeSphPrice();

            return round($base + $addTierPrice, 2);
        }

        if ($hasCyl) {
            return round(self::getProgressiveCylPrice(), 2);
        }

        if ($hasNegSph) {
            return round(self::getProgressiveNegativeSphPrice(), 2);
        }

        if ($hasAdd) {
            $effectivePlusSph = self::effectivePlusSphere($odSph, $osSph);

            return round(self::progressivePlusFlatPrice($effectivePlusSph, $effectiveAdd), 2);
        }

        return 0.0;
    }

    /**
     * Returns the diopter add-on price for a prescription.
     *
     * For progressive vision: ADD-driven tiers with flat or stacked pricing (see progressivePrescriptionAddOn).
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
        ?string $odAdd = null,
        ?string $osAdd = null,
    ): float {
        if ($vision === 'progressive') {
            return self::progressivePrescriptionAddOn($odSph, $odCyl, $osSph, $osCyl, $odAdd, $osAdd);
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
        foreach ($groups as $group => $rows) {
            if (! in_array($group, OpticalRxDiopterValue::groups(), true)) {
                continue;
            }

            $submittedValues = [];
            $seen = [];

            foreach ($rows as $index => $row) {
                $value = $group === OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD
                    ? self::normalizeAddValue($row['value'] ?? '')
                    : self::normalizeDiopterValue($row['value'] ?? '');

                if ($value === null) {
                    $message = $group === OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD
                        ? __('Enter a valid ADD value (0.00 to 10.00, e.g. 2.50).')
                        : __('Enter a valid diopter value (e.g. -6.00 or 7.25).');

                    throw ValidationException::withMessages([
                        "{$group}.{$index}.value" => $message,
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
                $price = $group === OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD
                    ? 0.0
                    : (is_numeric($row['price'] ?? null) ? round((float) $row['price'], 2) : 0.0);

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

    public static function normalizeAddValue(mixed $raw): ?string
    {
        $normalized = self::normalizeDiopterValue($raw);
        if ($normalized === null) {
            return null;
        }

        $value = (float) $normalized;
        if ($value < 0 || $value > 10.0) {
            return null;
        }

        return $normalized;
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
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD => OpticalRxOptions::optionsFromKeys(
                array_merge([OpticalRxOptions::UNKNOWN], OpticalRxOptions::progressiveAddKeys()),
            ),
            default => [OpticalRxOptions::UNKNOWN => '—'],
        };
    }

    public static function ensureProgressiveAddSeeded(): void
    {
        if (OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD)
            ->exists()) {
            return;
        }

        foreach (OpticalRxOptions::progressiveAddKeys() as $value) {
            OpticalRxDiopterValue::query()->updateOrCreate(
                ['group' => OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD, 'value' => $value],
                [
                    'price' => 0,
                    'sort_order' => self::sortOrderForValue($value),
                    'is_default' => true,
                ],
            );
        }

        self::clearCache();
    }

    public static function seedDefaults(): void
    {
        $legacyPrices = self::legacyPricesFromSettings();

        $definitions = [
            OpticalRxDiopterValue::GROUP_SINGLE_SPH => OpticalRxOptions::pricedSphereKeys('single'),
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH => OpticalRxOptions::pricedSphereKeys('progressive'),
            OpticalRxDiopterValue::GROUP_SINGLE_CYL => OpticalRxOptions::pricedCylinderKeys(),
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_CYL => OpticalRxOptions::pricedCylinderKeys(),
            OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD => OpticalRxOptions::progressiveAddKeys(),
        ];

        foreach ($definitions as $group => $values) {
            foreach ($values as $value) {
                $price = $group === OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD
                    ? 0.0
                    : ($legacyPrices[$group][$value] ?? 0.0);

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
     * @return list<array{value: string, is_default: bool}>
     */
    public static function addRowsForForm(): array
    {
        self::ensureSeeded();

        return OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->map(fn (OpticalRxDiopterValue $row): array => [
                'value' => $row->value,
                'is_default' => $row->is_default,
            ])
            ->values()
            ->all();
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
