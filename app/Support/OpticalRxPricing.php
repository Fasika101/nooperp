<?php

namespace App\Support;

/**
 * @deprecated Use OpticalRxConfig directly. Kept for backward compatibility.
 */
class OpticalRxPricing
{
    public const SETTING_KEY = 'optical_rx_diopter_prices';

    public const GROUP_SINGLE_SPH = OpticalRxDiopterValue::GROUP_SINGLE_SPH;

    public const GROUP_SINGLE_CYL = OpticalRxDiopterValue::GROUP_SINGLE_CYL;

    public const GROUP_PROGRESSIVE_SPH = OpticalRxDiopterValue::GROUP_PROGRESSIVE_SPH;

    public const GROUP_PROGRESSIVE_CYL = OpticalRxDiopterValue::GROUP_PROGRESSIVE_CYL;

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return OpticalRxDiopterValue::groups();
    }

    public static function groupLabel(string $group): string
    {
        return OpticalRxDiopterValue::groupOptions()[$group] ?? $group;
    }

    public static function priceForGroupValue(string $group, ?string $value): float
    {
        return OpticalRxConfig::priceForGroupValue($group, $value);
    }

    public static function sphereGroupForVision(string $vision): string
    {
        return OpticalRxConfig::sphereGroupForVision($vision);
    }

    public static function cylinderGroupForVision(string $vision): string
    {
        return OpticalRxConfig::cylinderGroupForVision($vision);
    }

    public static function eyeAddOn(string $vision, ?string $sph, ?string $cyl): float
    {
        return OpticalRxConfig::eyeAddOn($vision, $sph, $cyl);
    }

    public static function prescriptionAddOn(
        string $vision,
        ?string $odSph,
        ?string $odCyl,
        ?string $osSph,
        ?string $osCyl,
        ?string $odAdd = null,
        ?string $osAdd = null,
    ): float {
        return OpticalRxConfig::prescriptionAddOn($vision, $odSph, $odCyl, $osSph, $osCyl, $odAdd, $osAdd);
    }
}
