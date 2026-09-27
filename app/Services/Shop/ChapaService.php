<?php

namespace App\Services\Shop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChapaService
{
    public function isConfigured(): bool
    {
        return filled(config('storefront.chapa.secret_key'));
    }

    /**
     * @return array{ok:bool,checkout_url:?string,tx_ref:?string,error:?string}
     */
    public function initialize(float $amount, array $customer, string $txRef, string $callbackUrl, string $returnUrl): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'checkout_url' => null, 'tx_ref' => null, 'error' => 'Chapa is not configured (CHAPA_SECRET_KEY).'];
        }

        $secret = (string) config('storefront.chapa.secret_key');
        $base = rtrim((string) config('storefront.chapa.base_url'), '/');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(45)
            ->post($base.'/transaction/initialize', [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => config('storefront.currency', 'ETB'),
                'email' => $customer['email'] ?? ($this->fallbackEmail($customer['phone'] ?? 'guest')),
                'first_name' => $this->firstName($customer['name'] ?? 'Customer'),
                'last_name' => $this->lastName($customer['name'] ?? 'Customer'),
                'phone_number' => $customer['phone'] ?? null,
                'tx_ref' => $txRef,
                'callback_url' => $callbackUrl,
                'return_url' => $returnUrl,
                'customization' => [
                    'title' => config('storefront.brand_name', 'Liba Optics'),
                    'description' => 'Online eyewear order',
                ],
            ]);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'checkout_url' => null,
                'tx_ref' => $txRef,
                'error' => $response->json('message') ?? $response->body(),
            ];
        }

        $data = $response->json();
        $url = $data['data']['checkout_url'] ?? null;
        if (($data['status'] ?? null) !== 'success' || ! $url) {
            return [
                'ok' => false,
                'checkout_url' => null,
                'tx_ref' => $txRef,
                'error' => is_string($data['message'] ?? null) ? $data['message'] : 'Could not start Chapa checkout.',
            ];
        }

        return ['ok' => true, 'checkout_url' => $url, 'tx_ref' => $txRef, 'error' => null];
    }

    /**
     * @return array{ok:bool,verified:bool,error:?string}
     */
    public function verify(string $txRef): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'verified' => false, 'error' => 'Chapa is not configured.'];
        }

        $secret = (string) config('storefront.chapa.secret_key');
        $base = rtrim((string) config('storefront.chapa.base_url'), '/');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(45)
            ->get($base.'/transaction/verify/'.urlencode($txRef));

        if (! $response->successful()) {
            return ['ok' => false, 'verified' => false, 'error' => $response->body()];
        }

        $data = $response->json();
        $verified = ($data['status'] ?? null) === 'success'
            && ($data['data']['status'] ?? null) === 'success';

        return ['ok' => true, 'verified' => $verified, 'error' => null];
    }

    public function makeTxRef(): string
    {
        return 'shop-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8));
    }

    protected function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: ['Customer'];

        return $parts[0] ?: 'Customer';
    }

    protected function lastName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: ['Customer'];

        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer';
    }

    protected function fallbackEmail(string $phone): string
    {
        $safe = preg_replace('/\D+/', '', $phone) ?: 'guest';

        return $safe.'@shop.local';
    }
}
