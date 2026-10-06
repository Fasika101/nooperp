<?php

namespace App\Services\Shop;

use App\Support\OpticalRxConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiPrescriptionScanner
{
    public function isConfigured(): bool
    {
        return filled(config('storefront.gemini.api_key'));
    }

    /**
     * @return array{
     *   is_prescription: bool,
     *   vision_type: string,
     *   right_eye: array{sph:?string,cyl:?string,axis:?int|string,add:?string},
     *   left_eye: array{sph:?string,cyl:?string,axis:?int|string,add:?string},
     *   pd: array{type:string,one:?float,right:?float,left:?float},
     *   confidence: string,
     *   notes: string
     * }
     */
    public function scan(UploadedFile $file): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Prescription scanning is not configured. Add GEMINI_API_KEY to .env.');
        }

        $mime = $file->getMimeType() ?: 'image/jpeg';
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Only JPG, PNG, GIF or WEBP images are allowed.');
        }

        $imageBase64 = base64_encode((string) file_get_contents($file->getRealPath()));
        $model = (string) config('storefront.gemini.model', 'gemini-2.0-flash');
        $apiKey = (string) config('storefront.gemini.api_key');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $this->prompt()],
                    ['inline_data' => ['mime_type' => $mime, 'data' => $imageBase64]],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->responseSchema(),
            ],
        ];

        $response = Http::timeout(90)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
            ->post($url, $payload);

        if (! $response->successful()) {
            $msg = $response->json('error.message') ?? ('HTTP '.$response->status());
            throw new RuntimeException('Scanning service error: '.$msg);
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('The scanning service returned an unexpected response.');
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new RuntimeException('Could not parse the scanned prescription data.');
        }

        if (isset($data['is_prescription']) && $data['is_prescription'] === false) {
            throw new RuntimeException('This image does not look like an eyeglasses prescription. Please upload a clear photo.');
        }

        return $this->normalize($data);
    }

    /**
     * Price lenses from ERP Optical Rx config using scanned values.
     *
     * @deprecated Prefer quoteFromScan() for exact / range / quote modes.
     */
    public function priceFromScan(array $scan): float
    {
        $quote = $this->quoteFromScan($scan);

        return (float) ($quote['price'] ?? 0);
    }

    /**
     * Build an ERP-based lens price quote from a scan.
     *
     * @return array{
     *   mode: 'exact'|'range'|'quote',
     *   price: float,
     *   price_min: ?float,
     *   price_max: ?float,
     *   label: string,
     *   note: string,
     *   vision: string
     * }
     */
    public function quoteFromScan(array $scan): array
    {
        $confidence = (string) ($scan['confidence'] ?? 'medium');
        $visionRaw = (string) ($scan['vision_type_raw'] ?? $scan['vision_type'] ?? 'single');
        $vision = ($scan['vision_type'] ?? 'single') === 'progressive' ? 'progressive' : 'single';

        $odSph = $scan['right_eye']['sph'] ?? null;
        $odCyl = $scan['right_eye']['cyl'] ?? null;
        $odAdd = $scan['right_eye']['add'] ?? null;
        $osSph = $scan['left_eye']['sph'] ?? null;
        $osCyl = $scan['left_eye']['cyl'] ?? null;
        $osAdd = $scan['left_eye']['add'] ?? null;

        $primary = OpticalRxConfig::prescriptionAddOn($vision, $odSph, $odCyl, $osSph, $osCyl, $odAdd, $osAdd);

        // Beyond ERP tiers — POS would enter a custom price
        if ($primary === OpticalRxConfig::COMPOUND_CUSTOM_SENTINEL) {
            $tier1 = OpticalRxConfig::getCompoundSvTier1Price();
            $tier2 = OpticalRxConfig::getCompoundSvTier2Price();

            return [
                'mode' => 'quote',
                'price' => 0.0,
                'price_min' => $tier2 > 0 ? $tier2 : ($tier1 > 0 ? $tier1 : null),
                'price_max' => null,
                'label' => 'Quote after review',
                'note' => 'This prescription needs a lab review. Frame + coating can be paid now; final lens price is confirmed later.',
                'vision' => $vision,
            ];
        }

        $candidates = [];
        if ($primary > 0) {
            $candidates[] = $primary;
        }

        // If Gemini was unsure about single vs progressive, price both
        if ($visionRaw === 'unknown' || $confidence !== 'high') {
            $altVision = $vision === 'progressive' ? 'single' : 'progressive';
            $alt = OpticalRxConfig::prescriptionAddOn($altVision, $odSph, $odCyl, $osSph, $osCyl, $odAdd, $osAdd);
            if ($alt !== OpticalRxConfig::COMPOUND_CUSTOM_SENTINEL && $alt > 0) {
                $candidates[] = $alt;
            }
        }

        // Single-vision compound: nearby tier as a soft range when confidence isn't high
        if ($vision === 'single' && $confidence !== 'high') {
            $hasSph = filled($odSph) || filled($osSph);
            $hasCyl = filled($odCyl) || filled($osCyl);
            if ($hasSph && $hasCyl) {
                $t1 = OpticalRxConfig::getCompoundSvTier1Price();
                $t2 = OpticalRxConfig::getCompoundSvTier2Price();
                if ($t1 > 0) {
                    $candidates[] = $t1;
                }
                if ($t2 > 0) {
                    $candidates[] = $t2;
                }
            }
        }

        // Progressive: when ADD is present, include both ADD tiers as a soft range
        if ($vision === 'progressive' && $confidence !== 'high') {
            $hasAdd = filled($odAdd) || filled($osAdd);
            if ($hasAdd) {
                $a1 = OpticalRxConfig::getProgressiveAddTier1Price();
                $a2 = OpticalRxConfig::getProgressiveAddTier2Price();
                if ($a1 > 0) {
                    $candidates[] = $a1;
                }
                if ($a2 > 0) {
                    $candidates[] = $a2;
                }
                // If primary already stacked (−SPH/CYL + ADD), shift by the other ADD tier delta
                if ($primary > 0 && $a1 > 0 && $a2 > 0) {
                    $candidates[] = max(0, $primary + ($a2 - $a1));
                    $candidates[] = max(0, $primary - ($a2 - $a1));
                }
            }
        }

        $candidates = array_values(array_unique(array_map(fn ($v) => round((float) $v, 2), $candidates)));
        sort($candidates);

        if ($candidates === []) {
            return [
                'mode' => 'quote',
                'price' => 0.0,
                'price_min' => null,
                'price_max' => null,
                'label' => 'Quote after review',
                'note' => 'We could not match this prescription to standard lens prices. Pay for the frame now — lens price confirmed after review.',
                'vision' => $vision,
            ];
        }

        $min = $candidates[0];
        $max = $candidates[array_key_last($candidates)];
        $charge = $primary > 0 ? round($primary, 2) : round(($min + $max) / 2, 2);

        // Exact when high confidence and a single clear ERP price
        if ($confidence === 'high' && abs($max - $min) < 0.01 && $primary > 0) {
            return [
                'mode' => 'exact',
                'price' => round($primary, 2),
                'price_min' => round($primary, 2),
                'price_max' => round($primary, 2),
                'label' => 'Lens price',
                'note' => 'Priced from your scanned prescription.',
                'vision' => $vision,
            ];
        }

        // Still treat as exact if min==max even with medium confidence
        if (abs($max - $min) < 0.01) {
            return [
                'mode' => 'exact',
                'price' => $min,
                'price_min' => $min,
                'price_max' => $max,
                'label' => 'Lens price',
                'note' => 'Priced from your scanned prescription.',
                'vision' => $vision,
            ];
        }

        return [
            'mode' => 'range',
            'price' => $charge,
            'price_min' => $min,
            'price_max' => $max,
            'label' => 'Estimated lens range',
            'note' => $confidence === 'low'
                ? 'Image was hard to read — final lens price may be adjusted after review.'
                : 'Estimated from your scanned prescription. Final price confirmed if values need a lab check.',
            'vision' => $vision,
        ];
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
You are an optical prescription reader. Analyze this photo of an eyeglasses
prescription (it may be handwritten or printed) and extract the values.

Rules:
- OD = right eye, OS = left eye. R/RE = right, L/LE = left.
- SPH (sphere), CYL (cylinder) and ADD values must be returned as signed decimals
  rounded to the nearest 0.25 step, formatted like "-2.50", "+1.75" or "0.00".
  A value written as "plano", "pl" or 0 means "0.00".
- AXIS is an integer between 0 and 180 (no degree sign).
- PD (pupillary distance) may be one combined number (e.g. 63) or two monocular
  numbers for right/left (e.g. 31.5/31.0).
- vision_type: "progressive" if there is an ADD / NEAR / bifocal / progressive
  indication, "single" if it is clearly distance-only or reading-only single
  vision, otherwise "unknown".
- Use null for any value that is not present or not readable. NEVER guess.
- confidence: "high" if everything was clearly readable, "medium" if some values
  were hard to read, "low" if the image is blurry or barely readable.
- notes: one short sentence about anything the customer should double check
  (empty string if nothing).
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        $eye = [
            'type' => 'OBJECT',
            'properties' => [
                'sph' => ['type' => 'STRING', 'nullable' => true],
                'cyl' => ['type' => 'STRING', 'nullable' => true],
                'axis' => ['type' => 'INTEGER', 'nullable' => true],
                'add' => ['type' => 'STRING', 'nullable' => true],
            ],
        ];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'is_prescription' => ['type' => 'BOOLEAN'],
                'vision_type' => ['type' => 'STRING', 'enum' => ['single', 'progressive', 'unknown']],
                'right_eye' => $eye,
                'left_eye' => $eye,
                'pd' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'type' => ['type' => 'STRING', 'enum' => ['one', 'two', 'none']],
                        'one' => ['type' => 'NUMBER', 'nullable' => true],
                        'right' => ['type' => 'NUMBER', 'nullable' => true],
                        'left' => ['type' => 'NUMBER', 'nullable' => true],
                    ],
                ],
                'confidence' => ['type' => 'STRING', 'enum' => ['high', 'medium', 'low']],
                'notes' => ['type' => 'STRING'],
            ],
            'required' => ['is_prescription', 'vision_type', 'right_eye', 'left_eye', 'pd', 'confidence'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        $visionRaw = (string) ($data['vision_type'] ?? 'unknown');
        $vision = in_array($visionRaw, ['single', 'progressive'], true) ? $visionRaw : 'single';

        return [
            'is_prescription' => (bool) ($data['is_prescription'] ?? true),
            'vision_type' => $vision,
            'vision_type_raw' => in_array($visionRaw, ['single', 'progressive', 'unknown'], true) ? $visionRaw : 'unknown',
            'right_eye' => [
                'sph' => $this->normalizePower($data['right_eye']['sph'] ?? null),
                'cyl' => $this->normalizePower($data['right_eye']['cyl'] ?? null),
                'axis' => $data['right_eye']['axis'] ?? null,
                'add' => $this->normalizeAdd($data['right_eye']['add'] ?? null),
            ],
            'left_eye' => [
                'sph' => $this->normalizePower($data['left_eye']['sph'] ?? null),
                'cyl' => $this->normalizePower($data['left_eye']['cyl'] ?? null),
                'axis' => $data['left_eye']['axis'] ?? null,
                'add' => $this->normalizeAdd($data['left_eye']['add'] ?? null),
            ],
            'pd' => [
                'type' => $data['pd']['type'] ?? 'none',
                'one' => $data['pd']['one'] ?? null,
                'right' => $data['pd']['right'] ?? null,
                'left' => $data['pd']['left'] ?? null,
            ],
            'confidence' => in_array($data['confidence'] ?? '', ['high', 'medium', 'low'], true)
                ? $data['confidence']
                : 'medium',
            'notes' => (string) ($data['notes'] ?? ''),
        ];
    }

    protected function normalizePower(mixed $raw): ?string
    {
        $normalized = OpticalRxConfig::normalizeDiopterValue($raw);
        if ($normalized === null) {
            return null;
        }

        // Snap to nearest 0.25 so ERP diopter table lookups match
        $snapped = round(((float) $normalized) / 0.25) * 0.25;

        return number_format($snapped, 2, '.', '');
    }

    protected function normalizeAdd(mixed $raw): ?string
    {
        $normalized = OpticalRxConfig::normalizeAddValue($raw);
        if ($normalized === null) {
            return null;
        }

        $snapped = round(((float) $normalized) / 0.25) * 0.25;
        if ($snapped < 0 || $snapped > 10.0) {
            return null;
        }

        return number_format($snapped, 2, '.', '');
    }
}
