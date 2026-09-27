<?php

namespace App\Services\Shop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsEthiopiaService
{
    public function isConfigured(): bool
    {
        return filled(config('storefront.sms_ethiopia.api_key'));
    }

    /**
     * Send a short SMS (ref + amount). Phone may be +251… or 251…
     *
     * @return array{ok:bool,error:?string}
     */
    public function send(string $phone, string $text): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'SMS Ethiopia is not configured (SMSETHIOPIA_API_KEY).'];
        }

        $msisdn = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($msisdn, '0') && strlen($msisdn) === 10) {
            $msisdn = '251'.substr($msisdn, 1);
        }
        if (! str_starts_with($msisdn, '251') || strlen($msisdn) < 12) {
            return ['ok' => false, 'error' => 'Invalid phone for SMS.'];
        }

        $base = rtrim((string) config('storefront.sms_ethiopia.base_url', 'https://smsethiopia.com/api'), '/');
        $key = (string) config('storefront.sms_ethiopia.api_key');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'KEY' => $key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($base.'/sms/send', [
                    'msisdn' => $msisdn,
                    'text' => $text,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SMS Ethiopia request failed: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            $err = $response->json('description') ?? $response->json('message') ?? $response->body();

            return ['ok' => false, 'error' => is_string($err) ? $err : 'SMS send failed'];
        }

        $json = $response->json();
        if (isset($json['sent']) && $json['sent'] === false) {
            return ['ok' => false, 'error' => (string) ($json['description'] ?? 'SMS not accepted')];
        }

        return ['ok' => true, 'error' => null];
    }
}
