<?php

namespace App\Support;

class OpticalRxOptions
{
    public const UNKNOWN = '-';

    /**
     * @return list<string>
     */
    public static function sphereKeys(string $vision): array
    {
        if ($vision === 'progressive') {
            return self::quarterDiopterKeys(-3.0, 3.0);
        }

        return self::quarterDiopterKeys(-6.0, 6.0);
    }

    /**
     * @return list<string>
     */
    public static function cylinderKeys(): array
    {
        return self::quarterDiopterKeys(-3.0, 3.0);
    }

    /**
     * Default progressive ADD powers seeded for the POS dropdown (+0.25 to +10.00).
     *
     * @return list<string>
     */
    public static function progressiveAddKeys(): array
    {
        return self::positiveQuarterDiopterKeys(0.25, 10.0);
    }

    /**
     * @return array<string, string>
     */
    public static function addOptions(): array
    {
        return OpticalRxConfig::addOptions();
    }

    /**
     * @return array<string, string> value => label (for &lt;select&gt; options)
     */
    public static function sphereOptions(string $vision): array
    {
        return OpticalRxConfig::sphereOptions($vision);
    }

    /**
     * @return array<string, string>
     */
    public static function cylinderOptions(string $vision = 'single'): array
    {
        return OpticalRxConfig::cylinderOptions($vision);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public static function optionsFromKeys(array $keys): array
    {
        $opts = [];
        foreach ($keys as $key) {
            $opts[$key] = self::formatLabel($key);
        }

        return $opts;
    }

    /**
     * @return list<string>
     */
    public static function quarterDiopterKeys(float $min, float $max): array
    {
        $keys = [self::UNKNOWN];
        $steps = (int) round(($max - $min) / 0.25);

        for ($n = 0; $n <= $steps; $n++) {
            $v = round($min + ($n * 0.25), 2);
            $keys[] = number_format($v, 2, '.', '');
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function positiveQuarterDiopterKeys(float $min, float $max): array
    {
        $keys = [];
        $steps = (int) round(($max - $min) / 0.25);

        for ($n = 0; $n <= $steps; $n++) {
            $v = round($min + ($n * 0.25), 2);
            $keys[] = number_format($v, 2, '.', '');
        }

        return $keys;
    }

    public static function formatLabel(string $key): string
    {
        if ($key === self::UNKNOWN) {
            return '—';
        }

        $v = (float) $key;
        if (abs($v) < 0.00001) {
            return '0.00';
        }

        $abs = number_format(abs($v), 2, '.', '');

        return $v < 0 ? '-'.$abs : '+'.$abs;
    }

    /**
     * @return list<string>
     */
    public static function pricedSphereKeys(string $vision): array
    {
        return array_values(array_filter(
            self::sphereKeys($vision),
            fn (string $key): bool => $key !== self::UNKNOWN,
        ));
    }

    /**
     * @return list<string>
     */
    public static function pricedCylinderKeys(): array
    {
        return array_values(array_filter(
            self::cylinderKeys(),
            fn (string $key): bool => $key !== self::UNKNOWN,
        ));
    }
}
