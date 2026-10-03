<?php

namespace App\Services\Shop;

use App\Models\OpticalLensNoPrescription;
use App\Models\ShopPrescription;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ShopCart
{
    public const SESSION_KEY = 'shop_cart';

    /**
     * @return array{
     *   gender_id:?int,
     *   items: list<array<string,mixed>>,
     *   prescription:?array<string,mixed>,
     *   lens_coating_id:?int,
     *   rx_batch_token:?string,
     *   customer: array{name:string,phone:string,address:string}
     * }
     */
    public static function get(): array
    {
        $cart = Session::get(self::SESSION_KEY, []);

        return [
            'gender_id' => $cart['gender_id'] ?? null,
            'items' => array_values($cart['items'] ?? []),
            'prescription' => $cart['prescription'] ?? null,
            'lens_coating_id' => isset($cart['lens_coating_id']) ? (int) $cart['lens_coating_id'] : null,
            'rx_batch_token' => $cart['rx_batch_token'] ?? null,
            'customer' => [
                'name' => $cart['customer']['name'] ?? '',
                'phone' => $cart['customer']['phone'] ?? '',
                'address' => $cart['customer']['address'] ?? '',
            ],
        ];
    }

    public static function put(array $cart): void
    {
        Session::put(self::SESSION_KEY, $cart);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function resetForNewVisit(): void
    {
        $cart = self::get();
        $cart['items'] = [];
        $cart['prescription'] = null;
        $cart['lens_coating_id'] = null;
        $cart['rx_batch_token'] = null;
        self::put($cart);
    }

    public static function setGender(int $genderId): void
    {
        $cart = self::get();
        $cart['gender_id'] = $genderId;
        $cart['items'] = [];
        $cart['prescription'] = null;
        $cart['lens_coating_id'] = null;
        $cart['rx_batch_token'] = null;
        self::put($cart);
    }

    public static function addItem(array $item): void
    {
        $cart = self::get();
        $cart['items'] = [$item];
        self::put($cart);
    }

    public static function removeItem(int $index): void
    {
        $cart = self::get();
        unset($cart['items'][$index]);
        $cart['items'] = array_values($cart['items']);
        self::put($cart);
    }

    public static function ensureRxBatchToken(): string
    {
        $cart = self::get();
        if (empty($cart['rx_batch_token'])) {
            $cart['rx_batch_token'] = 'rx-'.Str::lower(Str::random(24));
            self::put($cart);
        }

        return (string) $cart['rx_batch_token'];
    }

    /**
     * @param  array<string, mixed>  $scan
     * @param  array<string, mixed>|null  $pricing
     */
    public static function setPrescription(
        string $path,
        array $scan,
        float $price,
        ?int $prescriptionId = null,
        bool $scanned = true,
        ?array $pricing = null,
    ): void {
        $cart = self::get();
        $mode = (string) ($pricing['mode'] ?? 'exact');
        $charge = $mode === 'quote' ? 0.0 : max(0.0, round($price, 2));
        if ($charge < 0) {
            $charge = 0.0;
        }

        $cart['prescription'] = [
            'path' => $path,
            'scanned' => $scanned,
            'scan' => $scan,
            'price' => $charge,
            'price_min' => isset($pricing['price_min']) ? (float) $pricing['price_min'] : null,
            'price_max' => isset($pricing['price_max']) ? (float) $pricing['price_max'] : null,
            'price_mode' => $mode,
            'price_label' => (string) ($pricing['label'] ?? 'Lens price'),
            'price_note' => (string) ($pricing['note'] ?? ''),
            'prescription_id' => $prescriptionId,
            'vision_type' => $scan['vision_type'] ?? null,
            'confidence' => $scan['confidence'] ?? null,
        ];
        self::put($cart);
    }

    public static function clearPrescription(): void
    {
        $cart = self::get();
        $cart['prescription'] = null;
        self::put($cart);
    }

    public static function setLensCoating(?int $lensId): void
    {
        $cart = self::get();
        $cart['lens_coating_id'] = $lensId;
        self::put($cart);
    }

    public static function setCustomer(string $name, string $phone, string $address): void
    {
        $cart = self::get();
        $cart['customer'] = compact('name', 'phone', 'address');
        self::put($cart);
    }

    public static function lensPrice(?int $lensId = null): float
    {
        $id = $lensId ?? self::get()['lens_coating_id'] ?? null;
        if (! $id) {
            return 0.0;
        }

        $price = OpticalLensNoPrescription::query()
            ->where('is_active', true)
            ->whereKey($id)
            ->value('price');

        return $price !== null ? round((float) $price, 2) : 0.0;
    }

    /**
     * @return array{subtotal:float,prescription:float,lens:float,total:float,item_count:int}
     */
    public static function totals(): array
    {
        $cart = self::get();
        $subtotal = 0.0;
        foreach ($cart['items'] as $item) {
            $subtotal += (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }

        $prescription = 0.0;
        if (! empty($cart['prescription']['scanned'])) {
            $mode = (string) ($cart['prescription']['price_mode'] ?? 'exact');
            if ($mode === 'quote') {
                $prescription = 0.0;
            } else {
                $prescription = isset($cart['prescription']['price'])
                    ? (float) $cart['prescription']['price']
                    : (float) config('storefront.prescription_price', 0);
            }
            if ($prescription < 0) {
                $prescription = 0.0;
            }
        }

        $lens = self::lensPrice($cart['lens_coating_id'] ?? null);

        return [
            'subtotal' => round($subtotal, 2),
            'prescription' => round($prescription, 2),
            'lens' => $lens,
            'total' => round($subtotal + $prescription + $lens, 2),
            'item_count' => count($cart['items']),
        ];
    }

    public static function attachPrescriptionsToOrder(int $orderId): void
    {
        $cart = self::get();
        $token = $cart['rx_batch_token'] ?? null;
        if (! $token) {
            return;
        }

        ShopPrescription::query()
            ->where('batch_token', $token)
            ->whereNull('order_id')
            ->update(['order_id' => $orderId]);
    }
}
