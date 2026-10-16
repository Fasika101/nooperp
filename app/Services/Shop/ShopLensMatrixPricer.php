<?php

namespace App\Services\Shop;

/**
 * Shop-scanner-only lens pricing from the business matrix (hardcoded).
 * Does NOT affect ERP POS / OpticalRxConfig.
 *
 * Prices are per PAIR and already include coating (HMC vs Bluecut).
 */
class ShopLensMatrixPricer
{
    public const COATING_HMC = 'hmc';

    public const COATING_BLUECUT = 'bluecut';

    public const UNAVAILABLE = -1.0;

    /**
     * @param  array<string, mixed>  $scan
     * @return array{
     *   mode: 'exact'|'range'|'quote',
     *   price: float,
     *   price_min: ?float,
     *   price_max: ?float,
     *   label: string,
     *   note: string,
     *   vision: 'single'|'progressive',
     *   vision_label: string,
     *   coating: 'hmc'|'bluecut',
     *   coating_label: string,
     *   lens_type: ?string,
     *   od_type: ?string,
     *   os_type: ?string,
     *   available: bool,
     *   pricing_source: string
     * }
     */
    public function quote(array $scan, ?string $coating = null): array
    {
        // Scan-only coating: ignore customer choice; Rule 9/10/11/12 from Rx text
        $detected = $this->detectCoatingFromText(
            (string) ($scan['notes'] ?? ''),
            (string) ($scan['coating_hint'] ?? ''),
        );
        $coating = $this->normalizeCoating($coating ?? $detected);

        $confidence = (string) ($scan['confidence'] ?? 'medium');
        $vision = $this->resolveVision($scan);
        $odSph = $scan['right_eye']['sph'] ?? null;
        $odCyl = $scan['right_eye']['cyl'] ?? null;
        $odAdd = $scan['right_eye']['add'] ?? null;
        $osSph = $scan['left_eye']['sph'] ?? null;
        $osCyl = $scan['left_eye']['cyl'] ?? null;
        $osAdd = $scan['left_eye']['add'] ?? null;

        $isProgressive = $vision === 'progressive';
        $odType = $this->classifyEye($odSph, $odCyl, $isProgressive);
        $osType = $this->classifyEye($osSph, $osCyl, $isProgressive);

        $primary = $this->pairPrice(
            $odType,
            $osType,
            $odSph,
            $odCyl,
            $osSph,
            $osCyl,
            $coating,
        );

        $candidates = [];
        if ($primary !== self::UNAVAILABLE && $primary > 0) {
            $candidates[] = $primary;
        }

        $visionRaw = (string) ($scan['vision_type_raw'] ?? $scan['vision_type'] ?? 'single');
        if (($visionRaw === 'unknown' || $confidence !== 'high') && ! $this->hasAddPower($odAdd, $osAdd)) {
            $altProgressive = ! $isProgressive;
            $altOd = $this->classifyEye($odSph, $odCyl, $altProgressive);
            $altOs = $this->classifyEye($osSph, $osCyl, $altProgressive);
            $alt = $this->pairPrice($altOd, $altOs, $odSph, $odCyl, $osSph, $osCyl, $coating);
            if ($alt !== self::UNAVAILABLE && $alt > 0) {
                $candidates[] = $alt;
            }
        }

        $visionLabel = $vision === 'progressive' ? 'Progressive vision' : 'Single vision';
        $coatingLabel = $coating === self::COATING_BLUECUT ? 'Bluecut' : 'HMC';
        $lensType = $this->pairLensTypeLabel($odType, $osType);

        if ($primary === self::UNAVAILABLE) {
            return $this->result([
                'mode' => 'quote',
                'price' => 0.0,
                'price_min' => null,
                'price_max' => null,
                'label' => 'Not available for '.$coatingLabel,
                'note' => $coatingLabel.' is not offered for this prescription power. Choose HMC, or pay the frame now and confirm lenses with the lab.',
                'vision' => $vision,
                'vision_label' => $visionLabel,
                'coating' => $coating,
                'coating_label' => $coatingLabel,
                'lens_type' => $lensType,
                'od_type' => $odType,
                'os_type' => $osType,
                'available' => false,
            ]);
        }

        $candidates = array_values(array_unique(array_map(fn ($v) => round((float) $v, 2), $candidates)));
        sort($candidates);

        if ($candidates === []) {
            return $this->result([
                'mode' => 'quote',
                'price' => 0.0,
                'price_min' => null,
                'price_max' => null,
                'label' => 'Quote after review',
                'note' => 'We could not match this prescription to standard lens prices. Pay for the frame now — lens price confirmed after review.',
                'vision' => $vision,
                'vision_label' => $visionLabel,
                'coating' => $coating,
                'coating_label' => $coatingLabel,
                'lens_type' => $lensType,
                'od_type' => $odType,
                'os_type' => $osType,
                'available' => false,
            ]);
        }

        $min = $candidates[0];
        $max = $candidates[array_key_last($candidates)];
        $charge = $primary > 0 ? round($primary, 2) : round(($min + $max) / 2, 2);

        if (abs($max - $min) < 0.01) {
            return $this->result([
                'mode' => 'exact',
                'price' => $min,
                'price_min' => $min,
                'price_max' => $max,
                'label' => 'Lens price ('.$coatingLabel.')',
                'note' => 'Priced from your scanned '.$visionLabel.' prescription with '.$coatingLabel.' coating.',
                'vision' => $vision,
                'vision_label' => $visionLabel,
                'coating' => $coating,
                'coating_label' => $coatingLabel,
                'lens_type' => $lensType,
                'od_type' => $odType,
                'os_type' => $osType,
                'available' => true,
            ]);
        }

        return $this->result([
            'mode' => 'range',
            'price' => $charge,
            'price_min' => $min,
            'price_max' => $max,
            'label' => 'Estimated lens range ('.$coatingLabel.')',
            'note' => $confidence === 'low'
                ? 'Image was hard to read — final lens price may be adjusted after review.'
                : 'Estimated from your scanned '.$visionLabel.' prescription. Final price confirmed if values need a lab check.',
            'vision' => $vision,
            'vision_label' => $visionLabel,
            'coating' => $coating,
            'coating_label' => $coatingLabel,
            'lens_type' => $lensType,
            'od_type' => $odType,
            'os_type' => $osType,
            'available' => true,
        ]);
    }

    /**
     * Rules 9–12: detect coating from free text.
     * Rule 10 Bluecut synonyms win over Rule 9 HMC synonyms (Rule 11).
     * No coating word → HMC (Rule 12).
     */
    public function detectCoatingFromText(string ...$texts): string
    {
        $blob = strtolower(implode(' ', array_filter($texts, fn ($t) => is_string($t) && $t !== '')));
        if ($blob === '') {
            return self::COATING_HMC;
        }

        // Rule 10 — Bluecut (checked first so Rule 11 is automatic)
        if (preg_match(self::bluecutPattern(), $blob)) {
            return self::COATING_BLUECUT;
        }

        // Rule 9 HMC synonyms or anything else / blank → HMC (Rule 12)
        return self::COATING_HMC;
    }

    /**
     * True when the text contains an explicit Rule 9 HMC synonym.
     */
    public function mentionsHmcSynonym(string ...$texts): bool
    {
        $blob = strtolower(implode(' ', array_filter($texts, fn ($t) => is_string($t) && $t !== '')));

        return $blob !== '' && (bool) preg_match(self::hmcPattern(), $blob);
    }

    /**
     * True when the text contains a Rule 10 Bluecut synonym.
     */
    public function mentionsBluecutSynonym(string ...$texts): bool
    {
        $blob = strtolower(implode(' ', array_filter($texts, fn ($t) => is_string($t) && $t !== '')));

        return $blob !== '' && (bool) preg_match(self::bluecutPattern(), $blob);
    }

    protected static function bluecutPattern(): string
    {
        return '/\b('
            .'blue\s*-?\s*cut|bluecut'
            .'|blue\s*-?\s*light|bluelight|blue\s+light\s+filter'
            .'|blue\s*-?\s*block(?:er)?s?|blueblock'
            .'|anti[\s-]?blue'
            .'|blue\s*-?\s*guard|blueguard'
            .'|blue\s*-?\s*protect(?:ion)?'
            .'|blue\s*-?\s*ray\s*-?\s*cut'
            .'|bc|bl'
            .')\b/i';
    }

    protected static function hmcPattern(): string
    {
        return '/\b('
            .'hmc|shmc|hc|mc'
            .'|multi[\s-]?coat(?:ing)?'
            .'|hard\s+(?:multi[\s-]?)?coat(?:ing)?'
            .'|ag|ar|arc'
            .'|anti[\s-]?glare|antiglare|glare[\s-]?free'
            .'|anti[\s-]?reflective'
            .'|uc|uncoated|no\s+coating'
            .'|uv\s*-?\s*420|uv420'
            .'|white|clear|plain|normal|standard'
            .'|photochromic|photosolar|photo\s*-?\s*solar'
            .'|sunsensor|sun\s*-?\s*sensor'
            .'|auto[\s-]?grey|auto[\s-]?gray'
            .'|changeable|colour\s+change(?:t)?|color\s+change(?:t)?'
            .')\b/i';
    }

    public function normalizeCoating(?string $coating): string
    {
        $c = strtolower(trim((string) $coating));
        if (in_array($c, ['bluecut', 'blue_cut', 'blue-cut', 'blue'], true)) {
            return self::COATING_BLUECUT;
        }

        return self::COATING_HMC;
    }

    /**
     * Rules 13–14: ADD (and progressive markers) force progressive unless separate frames.
     *
     * @param  array<string, mixed>  $scan
     */
    public function resolveVision(array $scan): string
    {
        $notes = (string) ($scan['notes'] ?? '');
        $separate = ! empty($scan['separate_frames'])
            || (bool) preg_match('/\b(separate\s+frames?|two\s+pairs?|distance\s+only\s+frame|reading\s+only\s+frame)\b/i', $notes);

        $odAdd = $scan['right_eye']['add'] ?? null;
        $osAdd = $scan['left_eye']['add'] ?? null;
        $hasAdd = $this->hasAddPower($odAdd, $osAdd);

        if ($hasAdd && ! $separate) {
            return 'progressive';
        }

        $raw = (string) ($scan['vision_type_raw'] ?? $scan['vision_type'] ?? 'single');
        if ($raw === 'progressive') {
            return 'progressive';
        }

        return 'single';
    }

    /**
     * Classify one eye into a matrix lens type.
     */
    public function classifyEye(?string $sph, ?string $cyl, bool $progressive): string
    {
        $hasSph = $this->absPower($sph) >= 0.25;
        $hasCyl = $this->absPower($cyl) >= 0.25;

        if ($progressive) {
            if ($hasSph && $hasCyl) {
                return 'progressive_compound';
            }
            if ($hasCyl) {
                return 'progressive_cylindrical';
            }

            return 'progressive_spheric';
        }

        if ($hasSph && $hasCyl) {
            return 'compound';
        }
        if ($hasCyl) {
            return 'cylindrical';
        }
        if ($hasSph) {
            return 'spheric';
        }

        return 'plano';
    }

    /**
     * Pair price from OD/OS types (Rules 5–8).
     */
    public function pairPrice(
        string $odType,
        string $osType,
        ?string $odSph,
        ?string $odCyl,
        ?string $osSph,
        ?string $osCyl,
        string $coating,
    ): float {
        $od = $this->eyePairPrice($odType, $odSph, $odCyl, $coating);
        $os = $this->eyePairPrice($osType, $osSph, $osCyl, $coating);

        if ($od === self::UNAVAILABLE || $os === self::UNAVAILABLE) {
            return self::UNAVAILABLE;
        }

        // Rule 6: progressive cylindrical/compound overrides mixed-eye half pricing
        $overrideTypes = ['progressive_cylindrical', 'progressive_compound'];
        $odOverride = in_array($odType, $overrideTypes, true);
        $osOverride = in_array($osType, $overrideTypes, true);
        if ($odOverride || $osOverride) {
            $prices = [];
            if ($odOverride) {
                $prices[] = $od;
            }
            if ($osOverride) {
                $prices[] = $os;
            }

            return round(max($prices), 2);
        }

        // Same type → one pair price (higher eye band wins)
        if ($odType === $osType) {
            return round(max($od, $os), 2);
        }

        // Rule 5: mixed types → half of each pair price
        return round(($od / 2) + ($os / 2), 2);
    }

    /**
     * Full pair price as if both eyes were this type/power (for matrix lookup).
     */
    public function eyePairPrice(string $type, ?string $sph, ?string $cyl, string $coating): float
    {
        $coating = $this->normalizeCoating($coating);
        $sphAbs = $this->absPower($sph);
        $cylAbs = $this->absPower($cyl);

        return match ($type) {
            'plano' => $coating === self::COATING_BLUECUT ? 2500.0 : 2000.0,
            'spheric' => $this->sphericPrice($sphAbs, $coating),
            'cylindrical' => $this->cylindricalPrice($cylAbs, $coating),
            'compound' => $this->compoundPrice($sphAbs, $cylAbs, $coating),
            'progressive_spheric' => $coating === self::COATING_BLUECUT ? 5800.0 : 4500.0,
            'progressive_cylindrical', 'progressive_compound' => $coating === self::COATING_BLUECUT ? 9000.0 : 6800.0,
            default => self::UNAVAILABLE,
        };
    }

    protected function sphericPrice(float $sphAbs, string $coating): float
    {
        if ($sphAbs < 0.25) {
            return $coating === self::COATING_BLUECUT ? 2500.0 : 2000.0;
        }

        if ($sphAbs <= 4.00) {
            return $coating === self::COATING_BLUECUT ? 5800.0 : 2500.0;
        }

        // Bluecut only offered to 4.00
        if ($coating === self::COATING_BLUECUT) {
            return self::UNAVAILABLE;
        }

        if ($sphAbs <= 6.00) {
            return 3000.0;
        }
        if ($sphAbs <= 10.00) {
            return 3750.0;
        }
        if ($sphAbs <= 13.00) {
            return 4500.0;
        }
        if ($sphAbs <= 20.00) {
            return 6250.0;
        }

        return self::UNAVAILABLE;
    }

    protected function cylindricalPrice(float $cylAbs, string $coating): float
    {
        if ($cylAbs < 0.25) {
            return $coating === self::COATING_BLUECUT ? 2500.0 : 2000.0;
        }

        if ($coating === self::COATING_BLUECUT) {
            return $cylAbs <= 4.00 ? 5800.0 : self::UNAVAILABLE;
        }

        if ($cylAbs <= 1.75) {
            return 2500.0;
        }
        if ($cylAbs <= 3.00) {
            return 3250.0;
        }
        if ($cylAbs <= 4.00) {
            return 3750.0;
        }

        return self::UNAVAILABLE;
    }

    protected function compoundPrice(float $sphAbs, float $cylAbs, string $coating): float
    {
        $standard = $sphAbs <= 4.00 && $cylAbs <= 2.00;

        if ($standard) {
            return $coating === self::COATING_BLUECUT ? 5800.0 : 2500.0;
        }

        // High band: SPH > 4 OR CYL > 2 — Bluecut not available
        if ($coating === self::COATING_BLUECUT) {
            return self::UNAVAILABLE;
        }

        if ($sphAbs <= 20.00 && $cylAbs <= 6.00) {
            return 5000.0;
        }

        return self::UNAVAILABLE;
    }

    protected function absPower(?string $value): float
    {
        if ($value === null || $value === '' || $value === '-' || strcasecmp((string) $value, 'plano') === 0) {
            return 0.0;
        }

        return abs((float) $value);
    }

    protected function hasAddPower(?string $odAdd, ?string $osAdd): bool
    {
        return $this->absPower($odAdd) >= 0.25 || $this->absPower($osAdd) >= 0.25;
    }

    protected function pairLensTypeLabel(string $odType, string $osType): string
    {
        if ($odType === $osType) {
            return $odType;
        }

        return $odType.'+'.$osType;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function result(array $data): array
    {
        return array_merge($data, [
            'pricing_source' => 'shop_lens_matrix',
        ]);
    }
}
