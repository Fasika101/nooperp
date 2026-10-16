<?php

namespace Tests\Unit;

use App\Services\Shop\ShopLensMatrixPricer;
use PHPUnit\Framework\TestCase;

class ShopLensMatrixPricerTest extends TestCase
{
    protected ShopLensMatrixPricer $pricer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricer = new ShopLensMatrixPricer;
    }

    public function test_spheric_standard_hmc_and_bluecut(): void
    {
        $scan = $this->scan('-2.00', null, '-1.50', null, 'single');

        $hmc = $this->pricer->quote($scan, 'hmc');
        $this->assertTrue($hmc['available']);
        $this->assertSame('exact', $hmc['mode']);
        $this->assertSame(2500.0, $hmc['price']);
        $this->assertSame('single', $hmc['vision']);
        $this->assertSame('Single vision', $hmc['vision_label']);
        $this->assertSame('hmc', $hmc['coating']);

        $blue = $this->pricer->quote($scan, 'bluecut');
        $this->assertSame(5800.0, $blue['price']);
        $this->assertSame('bluecut', $blue['coating']);
    }

    public function test_spheric_above_four_bluecut_unavailable(): void
    {
        $scan = $this->scan('-5.00', null, '-5.00', null, 'single');
        $quote = $this->pricer->quote($scan, 'bluecut');

        $this->assertFalse($quote['available']);
        $this->assertSame('quote', $quote['mode']);
        $this->assertSame(0.0, $quote['price']);
    }

    public function test_spheric_high_band_hmc(): void
    {
        $scan = $this->scan('-7.00', null, '-7.00', null, 'single');
        $quote = $this->pricer->quote($scan, 'hmc');

        $this->assertSame(3750.0, $quote['price']);
    }

    public function test_compound_standard_and_high(): void
    {
        $standard = $this->pricer->quote($this->scan('-2.00', '-1.00', '-2.00', '-1.00', 'single'), 'hmc');
        $this->assertSame(2500.0, $standard['price']);

        $high = $this->pricer->quote($this->scan('-5.00', '-1.00', '-5.00', '-1.00', 'single'), 'hmc');
        $this->assertSame(5000.0, $high['price']);

        $blueHigh = $this->pricer->quote($this->scan('-5.00', '-1.00', '-5.00', '-1.00', 'single'), 'bluecut');
        $this->assertFalse($blueHigh['available']);
    }

    public function test_progressive_spheric_and_compound(): void
    {
        $spheric = $this->pricer->quote(
            $this->scan('-1.00', null, '-1.00', null, 'progressive', '2.00'),
            'hmc'
        );
        $this->assertSame('progressive', $spheric['vision']);
        $this->assertSame('Progressive vision', $spheric['vision_label']);
        $this->assertSame(4500.0, $spheric['price']);

        $compound = $this->pricer->quote(
            $this->scan('-1.00', '-0.75', '-1.00', '-0.75', 'progressive', '2.00'),
            'bluecut'
        );
        $this->assertSame(9000.0, $compound['price']);
    }

    public function test_add_overrides_single_to_progressive(): void
    {
        $scan = $this->scan('-1.00', null, '-1.00', null, 'single', '2.00');
        $quote = $this->pricer->quote($scan, 'hmc');

        $this->assertSame('progressive', $quote['vision']);
        $this->assertSame(4500.0, $quote['price']);
    }

    public function test_separate_frames_keeps_single_despite_add(): void
    {
        $scan = $this->scan('-1.00', null, '-1.00', null, 'single', '2.00');
        $scan['separate_frames'] = true;
        $quote = $this->pricer->quote($scan, 'hmc');

        $this->assertSame('single', $quote['vision']);
        $this->assertSame(2500.0, $quote['price']);
    }

    public function test_mixed_eyes_half_plus_half(): void
    {
        // OD spheric 2.00 HMC 2500, OS cylindrical 1.00 HMC 2500 → 1250+1250
        $scan = [
            'vision_type' => 'single',
            'vision_type_raw' => 'single',
            'confidence' => 'high',
            'notes' => '',
            'right_eye' => ['sph' => '-2.00', 'cyl' => null, 'add' => null],
            'left_eye' => ['sph' => '0.00', 'cyl' => '-1.00', 'add' => null],
        ];
        $quote = $this->pricer->quote($scan, 'hmc');

        $this->assertSame(2500.0, $quote['price']);
    }

    public function test_progressive_compound_override_mixed_eyes(): void
    {
        $scan = [
            'vision_type' => 'progressive',
            'vision_type_raw' => 'progressive',
            'confidence' => 'high',
            'notes' => '',
            'right_eye' => ['sph' => '-1.00', 'cyl' => '-0.50', 'add' => '2.00'],
            'left_eye' => ['sph' => '-1.00', 'cyl' => null, 'add' => '2.00'],
        ];
        $quote = $this->pricer->quote($scan, 'hmc');

        // Rule 6: progressive compound full pair price
        $this->assertSame(6800.0, $quote['price']);
    }

    public function test_detect_bluecut_wins_over_hmc(): void
    {
        $this->assertSame(
            ShopLensMatrixPricer::COATING_BLUECUT,
            $this->pricer->detectCoatingFromText('HMC with blue light filter')
        );
        $this->assertSame(
            ShopLensMatrixPricer::COATING_HMC,
            $this->pricer->detectCoatingFromText('multi coat hard coating')
        );
    }

    public function test_rule9_hmc_synonyms(): void
    {
        foreach (['HMC', 'AR', 'antiglare', 'UC', 'uncoated', 'UV420', 'photochromic', 'clear', 'standard', 'sunsensor'] as $word) {
            $this->assertSame(
                ShopLensMatrixPricer::COATING_HMC,
                $this->pricer->detectCoatingFromText($word),
                "Expected HMC for: {$word}"
            );
        }
    }

    public function test_rule10_bluecut_synonyms(): void
    {
        foreach (['blue cut', 'bluecut', 'blue blocker', 'anti-blue', 'blueguard', 'BC', 'BL', 'blue ray cut'] as $word) {
            $this->assertSame(
                ShopLensMatrixPricer::COATING_BLUECUT,
                $this->pricer->detectCoatingFromText($word),
                "Expected Bluecut for: {$word}"
            );
        }
    }

    public function test_uv420_is_hmc_not_bluecut(): void
    {
        $this->assertSame(
            ShopLensMatrixPricer::COATING_HMC,
            $this->pricer->detectCoatingFromText('lens UV420')
        );
    }

    public function test_scan_only_coating_from_notes_in_quote(): void
    {
        $scan = $this->scan('-2.00', null, '-1.50', null, 'single');
        $scan['notes'] = 'Blue blocker recommended';

        $quote = $this->pricer->quote($scan);
        $this->assertSame('bluecut', $quote['coating']);
        $this->assertSame(5800.0, $quote['price']);
    }

    public function test_plano_prices(): void
    {
        $scan = $this->scan('0.00', null, '0.00', null, 'single');
        $this->assertSame(2000.0, $this->pricer->quote($scan, 'hmc')['price']);
        $this->assertSame(2500.0, $this->pricer->quote($scan, 'bluecut')['price']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function scan(
        ?string $odSph,
        ?string $odCyl,
        ?string $osSph,
        ?string $osCyl,
        string $vision,
        ?string $add = null,
    ): array {
        return [
            'vision_type' => $vision,
            'vision_type_raw' => $vision,
            'confidence' => 'high',
            'notes' => '',
            'separate_frames' => false,
            'right_eye' => ['sph' => $odSph, 'cyl' => $odCyl, 'add' => $add],
            'left_eye' => ['sph' => $osSph, 'cyl' => $osCyl, 'add' => $add],
        ];
    }
}
