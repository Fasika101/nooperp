<?php

namespace Tests\Unit;

use App\Models\OpticalRxDiopterValue;
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

    public function test_prescription_add_on_sums_per_eye_sph_and_cyl(): void
    {
        OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_SINGLE_SPH)
            ->where('value', '-1.00')
            ->update(['price' => 100]);

        OpticalRxDiopterValue::query()
            ->where('group', OpticalRxDiopterValue::GROUP_SINGLE_CYL)
            ->where('value', '-0.50')
            ->update(['price' => 50]);

        OpticalRxConfig::clearCache();

        $total = OpticalRxConfig::prescriptionAddOn(
            'single',
            '-1.00',
            '-0.50',
            '0.00',
            '0.00',
        );

        $this->assertSame(150.0, $total);
    }

    public function test_unknown_diopter_values_add_zero(): void
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
}
