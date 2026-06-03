<?php

namespace App\Support;

use App\Models\OpticalRxDiopterValue;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OpticalRxConfig
{
    public const CACHE_KEY = 'optical_rx_config_v1';

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

    public static function prescriptionAddOn(
        string $vision,
        ?string $odSph,
        ?string $odCyl,
        ?string $osSph,
        ?string $osCyl,
    ): float {
        return round(
            self::eyeAddOn($vision, $odSph, $odCyl)
            + self::eyeAddOn($vision, $osSph, $osCyl),
            2,
        );
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
