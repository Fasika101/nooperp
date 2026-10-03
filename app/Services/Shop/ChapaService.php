<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        // Chapa: customization.title max 16 chars; phone prefers 09xxxxxxxx
        $title = (string) config('storefront.brand_name', 'Liba Optics');
        $title = mb_substr(trim($title) !== '' ? $title : 'Liba Optics', 0, 16);

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => config('storefront.currency', 'ETB'),
            'email' => $customer['email'] ?? ($this->fallbackEmail($customer['phone'] ?? 'guest')),
            'first_name' => $this->firstName($customer['name'] ?? 'Customer'),
            'last_name' => $this->lastName($customer['name'] ?? 'Customer'),
            'phone_number' => $this->chapaPhone($customer['phone'] ?? null),
            'tx_ref' => $txRef,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'customization' => [
                'title' => $title,
                'description' => 'Online eyewear order',
            ],
        ];

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(45)
            ->post($base.'/transaction/initialize', $payload);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();

            return [
                'ok' => false,
                'checkout_url' => null,
                'tx_ref' => $txRef,
                'error' => $this->stringifyError($message),
            ];
        }

        $data = $response->json();
        $url = $data['data']['checkout_url'] ?? null;
        if (($data['status'] ?? null) !== 'success' || ! $url) {
            return [
                'ok' => false,
                'checkout_url' => null,
                'tx_ref' => $txRef,
                'error' => $this->stringifyError($data['message'] ?? 'Could not start Chapa checkout.'),
            ];
        }

        return ['ok' => true, 'checkout_url' => $url, 'tx_ref' => $txRef, 'error' => null];
    }

    /**
     * Verify a transaction with Chapa and return useful payment fields.
     *
     * @return array{
     *   ok:bool,
     *   verified:bool,
     *   error:?string,
     *   reference:?string,
     *   tx_ref:?string,
     *   amount:?float,
     *   currency:?string,
     *   status:?string,
     *   raw:?array
     * }
     */
    public function verify(string $txRef): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'verified' => false,
                'error' => 'Chapa is not configured.',
                'reference' => null,
                'tx_ref' => null,
                'amount' => null,
                'currency' => null,
                'status' => null,
                'raw' => null,
            ];
        }

        $secret = (string) config('storefront.chapa.secret_key');
        $base = rtrim((string) config('storefront.chapa.base_url'), '/');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(45)
            ->get($base.'/transaction/verify/'.urlencode($txRef));

        if (! $response->successful()) {
            return [
                'ok' => false,
                'verified' => false,
                'error' => $response->body(),
                'reference' => null,
                'tx_ref' => null,
                'amount' => null,
                'currency' => null,
                'status' => null,
                'raw' => null,
            ];
        }

        $data = $response->json();
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];
        $status = isset($payload['status']) ? (string) $payload['status'] : null;
        $verified = ($data['status'] ?? null) === 'success' && $status === 'success';

        $reference = $payload['reference'] ?? $payload['chapa_reference'] ?? null;
        if (is_array($reference)) {
            $reference = $this->stringifyError($reference);
        }

        return [
            'ok' => true,
            'verified' => $verified,
            'error' => null,
            'reference' => is_string($reference) && $reference !== '' ? $reference : null,
            'tx_ref' => isset($payload['tx_ref']) ? (string) $payload['tx_ref'] : $txRef,
            'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
            'currency' => isset($payload['currency']) ? (string) $payload['currency'] : null,
            'status' => $status,
            'raw' => is_array($data) ? $data : null,
        ];
    }

    /**
     * Sequential + unique refs for Chapa.
     * Format: NOOP-000007-a3f9c2
     *
     * Chapa rejects any tx_ref that was ever initialized before — even abandoned
     * checkouts — so a short random suffix is required on top of the sequence.
     */
    public function makeTxRef(): string
    {
        return DB::transaction(function () {
            $row = Setting::query()->where('key', 'shop_noop_seq')->lockForUpdate()->first();
            $fromSetting = (int) ($row?->value ?? 0);
            $fromOrders = $this->highestNoopSeqFromOrders();
            $fromPending = $this->highestNoopSeqFromPending();
            $next = max($fromSetting, $fromOrders, $fromPending) + 1;

            Setting::query()->updateOrCreate(
                ['key' => 'shop_noop_seq'],
                ['value' => (string) $next],
            );
            Cache::forget('settings');

            $base = 'NOOP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            do {
                $ref = $base.'-'.strtolower(bin2hex(random_bytes(3)));
            } while (
                Order::query()->where('external_ref', $ref)->exists()
                || \Illuminate\Support\Facades\Storage::disk('local')->exists('shop/pending/'.$ref.'.json')
            );

            return $ref;
        });
    }

    protected function highestNoopSeqFromOrders(): int
    {
        $max = 0;
        $refs = Order::query()
            ->where('external_ref', 'like', 'NOOP-%')
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('external_ref');

        foreach ($refs as $ref) {
            if (is_string($ref) && preg_match('/^NOOP-(\d+)/', $ref, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    protected function highestNoopSeqFromPending(): int
    {
        $max = 0;
        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('shop/pending');
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^NOOP-(\d+)/', $name, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    public function errorLooksLikeDuplicateRef(?string $error): bool
    {
        if ($error === null || $error === '') {
            return false;
        }

        $hay = strtolower($error);

        return str_contains($hay, 'used before')
            || str_contains($hay, 'already been used')
            || str_contains($hay, 'already used')
            || (str_contains($hay, 'duplicate') && str_contains($hay, 'ref'));
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

        // Use a real TLD — Chapa rejects some synthetic domains like *.local
        return $safe.'@customer.email';
    }

    protected function chapaPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '251') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return $digits;
        }

        return $phone;
    }

    protected function stringifyError(mixed $error): string
    {
        if (is_string($error) && trim($error) !== '') {
            return $error;
        }

        if (is_array($error)) {
            $parts = [];
            array_walk_recursive($error, function ($value) use (&$parts) {
                if (is_string($value) && $value !== '') {
                    $parts[] = $value;
                } elseif (is_numeric($value)) {
                    $parts[] = (string) $value;
                }
            });

            if ($parts !== []) {
                return implode(' ', array_unique($parts));
            }

            $json = json_encode($error);

            return is_string($json) ? $json : 'Chapa payment error.';
        }

        return 'Chapa payment error.';
    }
}
