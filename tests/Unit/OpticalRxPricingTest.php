<?php

namespace Tests\Unit;

use App\Models\OpticalRxDiopterValue;
use App\Models\Setting;
use App\Support\OpticalRxConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalRxPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        OpticalRxConfig::seedDefaults();
    }

    public function test_single_vision_sph_only_uses_higher_eye_price(): void
    {
        OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_SINGLE_SPH)
            ->where('value', '-1.00')
            ->update(['price' => 100]);

        OpticalRxConfig::clearCache();

        $total = OpticalRxConfig::prescriptionAddOn('single', '-1.00', '-', '0.00', '-');

        $this->assertSame(100.0, $total);
    }

    public function test_single_vision_unknown_diopter_values_add_zero(): void
    {
        OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_SINGLE_SPH)
            ->where('value', '-1.00')
            ->update(['price' => 100]);

        OpticalRxConfig::clearCache();

        $total = OpticalRxConfig::prescriptionAddOn('single', '-', '-', '-1.00', '-');

        $this->assertSame(100.0, $total);
    }

    public function test_custom_diopter_value_appears_in_dropdown(): void
    {
        OpticalRxDiopterValue::query()->create([
            'group' => OpticalRxDiopterValue::GROUP_SINGLE_SPH,
            'value' => '7.25',
            'price' => 25,
            'sort_order' => 725,
            'is_default' => false,
        ]);

        OpticalRxConfig::clearCache();

        $options = OpticalRxConfig::sphereOptions('single');

        $this->assertArrayHasKey('7.25', $options);
        $this->assertSame(25.0, OpticalRxConfig::priceForGroupValue(OpticalRxDiopterValue::GROUP_SINGLE_SPH, '7.25'));
    }

    public function test_progressive_add_only_up_to_three_uses_tier_one(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '0.00',
            '-',
            '0.00',
            '-',
            '2.00',
            '-',
        );

        $this->assertSame(3600.0, $total);
    }

    public function test_progressive_add_above_three_uses_tier_two(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '0.00',
            '-',
            '0.00',
            '-',
            '3.50',
            '-',
        );

        $this->assertSame(4500.0, $total);
    }

    public function test_progressive_plus_sph_and_add_flat_tier_one(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '2.50',
            '-',
            '1.00',
            '-',
            '2.00',
            '1.50',
        );

        $this->assertSame(3600.0, $total);
    }

    public function test_progressive_plus_sph_and_add_flat_tier_two(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '4.00',
            '-',
            '3.50',
            '-',
            '3.50',
            '3.00',
        );

        $this->assertSame(4500.0, $total);
    }

    public function test_progressive_negative_sph_alone(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '-2.00',
            '-',
            '0.00',
            '-',
            '-',
            '-',
        );

        $this->assertSame(2900.0, $total);
    }

    public function test_progressive_cyl_alone(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '0.00',
            '-1.00',
            '0.00',
            '-',
            '-',
            '-',
        );

        $this->assertSame(2900.0, $total);
    }

    public function test_progressive_negative_sph_with_add_stacks(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '-2.00',
            '-',
            '0.00',
            '-',
            '2.00',
            '-',
        );

        $this->assertSame(6500.0, $total);
    }

    public function test_progressive_cyl_with_add_stacks(): void
    {
        $this->seedProgressivePrices();

        $total = OpticalRxConfig::prescriptionAddOn(
            'progressive',
            '0.00',
            '-1.00',
            '0.00',
            '-',
            '3.50',
            '-',
        );

        $this->assertSame(7400.0, $total);
    }

    public function test_progressive_add_options_include_values_up_to_ten(): void
    {
        OpticalRxConfig::ensureProgressiveAddSeeded();
        OpticalRxConfig::clearCache();

        $options = OpticalRxConfig::addOptions();

        $this->assertArrayHasKey('-', $options);
        $this->assertArrayHasKey('0.25', $options);
        $this->assertArrayHasKey('3.00', $options);
        $this->assertArrayHasKey('10.00', $options);
        $this->assertSame('+10.00', $options['10.00']);
        $this->assertArrayNotHasKey('10.25', $options);
    }

    protected function seedProgressivePrices(): void
    {
        Setting::set(OpticalRxConfig::PROGRESSIVE_ADD_TIER1_SETTING, 3600);
        Setting::set(OpticalRxConfig::PROGRESSIVE_ADD_TIER2_SETTING, 4500);
        Setting::set(OpticalRxConfig::PROGRESSIVE_NEGATIVE_SPH_SETTING, 2900);
        Setting::set(OpticalRxConfig::PROGRESSIVE_CYL_SETTING, 2900);
    }
}
