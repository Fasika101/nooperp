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
     */
    public function priceFromScan(array $scan): float
    {
        $vision = ($scan['vision_type'] ?? 'single') === 'progressive' ? 'progressive' : 'single';

        return OpticalRxConfig::prescriptionAddOn(
            $vision,
            $scan['right_eye']['sph'] ?? null,
            $scan['right_eye']['cyl'] ?? null,
            $scan['left_eye']['sph'] ?? null,
            $scan['left_eye']['cyl'] ?? null,
            $scan['right_eye']['add'] ?? null,
            $scan['left_eye']['add'] ?? null,
        );
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
        return [
            'is_prescription' => (bool) ($data['is_prescription'] ?? true),
            'vision_type' => in_array($data['vision_type'] ?? '', ['single', 'progressive'], true)
                ? $data['vision_type']
                : 'single',
            'right_eye' => [
                'sph' => $data['right_eye']['sph'] ?? null,
                'cyl' => $data['right_eye']['cyl'] ?? null,
                'axis' => $data['right_eye']['axis'] ?? null,
                'add' => $data['right_eye']['add'] ?? null,
            ],
            'left_eye' => [
                'sph' => $data['left_eye']['sph'] ?? null,
                'cyl' => $data['left_eye']['cyl'] ?? null,
                'axis' => $data['left_eye']['axis'] ?? null,
                'add' => $data['left_eye']['add'] ?? null,
            ],
            'pd' => [
                'type' => $data['pd']['type'] ?? 'none',
                'one' => $data['pd']['one'] ?? null,
                'right' => $data['pd']['right'] ?? null,
                'left' => $data['pd']['left'] ?? null,
            ],
            'confidence' => $data['confidence'] ?? 'medium',
            'notes' => (string) ($data['notes'] ?? ''),
        ];
    }
}
