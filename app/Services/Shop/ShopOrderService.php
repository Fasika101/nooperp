<?php

namespace App\Services\Shop;

use App\Models\BranchProductStock;
use App\Models\Customer;
use App\Models\OpticalLensNoPrescription;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopPrescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopOrderService
{
    /**
     * Create an online order from the session cart after payment is confirmed (or pending).
     *
     * @param  array{
     *   external_ref: string,
     *   payment_status?: string,
     *   payment_amount?: float|null,
     *   status?: string
     * }  $meta
     */
    public function createFromCart(array $cart, array $meta): Order
    {
        $existing = Order::query()->where('external_ref', $meta['external_ref'])->first();
        if ($existing) {
            return $existing;
        }

        $branchId = (int) config('storefront.branch_id');
        if ($branchId < 1) {
            throw ValidationException::withMessages(['branch' => 'Storefront branch is not configured.']);
        }

        if (empty($cart['items'])) {
            throw ValidationException::withMessages(['cart' => 'Cart is empty.']);
        }

        $customerPayload = $cart['customer'] ?? [];
        if (blank($customerPayload['name'] ?? null) || blank($customerPayload['phone'] ?? null) || blank($customerPayload['address'] ?? null)) {
            throw ValidationException::withMessages(['customer' => 'Name, phone and address are required.']);
        }

        return DB::transaction(function () use ($cart, $meta, $branchId, $customerPayload) {
            $customer = $this->resolveCustomer($customerPayload);
            $totals = $this->computeTotals($cart);
            $prepared = [];

            foreach ($cart['items'] as $item) {
                $product = Product::query()->whereKey($item['product_id'])->lockForUpdate()->first();
                if (! $product || $product->is_service) {
                    throw ValidationException::withMessages(['cart' => 'Invalid product in cart.']);
                }

                $colorOptionId = isset($item['color_option_id']) ? (int) $item['color_option_id'] : null;
                if ($colorOptionId !== null && $colorOptionId <= 0) {
                    $colorOptionId = null;
                }
                $sizeOptionId = isset($item['size_option_id']) ? (int) $item['size_option_id'] : null;
                if ($sizeOptionId !== null && $sizeOptionId <= 0) {
                    $sizeOptionId = null;
                }
                $qty = max(1, (int) ($item['quantity'] ?? 1));

                $variant = null;
                if (! empty($item['variant_id'])) {
                    $variant = ProductVariant::query()
                        ->whereKey((int) $item['variant_id'])
                        ->where('product_id', $product->id)
                        ->first();
                }

                if (! $variant) {
                    $resolved = app(ShopCatalogService::class)->resolveInStockVariant(
                        $product->id,
                        $colorOptionId,
                        $sizeOptionId
                    );
                    $variant = $resolved['variant'] ?? null;
                }

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'cart' => "Insufficient stock for {$product->name}",
                    ]);
                }

                $colorOptionId = $variant->color_option_id ? (int) $variant->color_option_id : null;
                $sizeOptionId = $variant->size_option_id ? (int) $variant->size_option_id : null;

                $branchStock = BranchProductStock::query()
                    ->where('branch_id', $branchId)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if (! $branchStock) {
                    $branchStock = BranchProductStock::create([
                        'branch_id' => $branchId,
                        'product_variant_id' => $variant->id,
                        'quantity' => 0,
                    ]);
                }

                if ($branchStock->quantity < $qty) {
                    throw ValidationException::withMessages([
                        'cart' => "Insufficient stock for {$product->name}",
                    ]);
                }

                $branchStock->decrement('quantity', $qty);

                $prepared[] = [
                    'product_id' => $product->id,
                    'size_option_id' => $sizeOptionId,
                    'color_option_id' => $colorOptionId,
                    'line_label' => $item['name'] ?? $product->formatNameWithVariant($sizeOptionId, $colorOptionId),
                    'quantity' => $qty,
                    'price' => round((float) ($item['price'] ?? $product->price), 2),
                    'unit_cost' => (float) ($product->cost_price ?? 0),
                    'optical_meta' => null,
                ];
            }

            // Prescription + lens coating as service lines
            $serviceId = Product::opticalServiceProductId();
            $opticalMeta = [
                'route' => 'shop',
                'source' => 'in_app_shop',
                'prescription' => $cart['prescription']['scan'] ?? ($cart['prescription'] ?? null),
                'lens_coating_id' => $cart['lens_coating_id'] ?? null,
                'vision_type' => $cart['prescription']['vision_type'] ?? null,
            ];

            if ($totals['prescription'] > 0 && $serviceId) {
                $prepared[] = [
                    'product_id' => $serviceId,
                    'size_option_id' => null,
                    'color_option_id' => null,
                    'line_label' => 'Prescription lenses',
                    'quantity' => 1,
                    'price' => $totals['prescription'],
                    'unit_cost' => 0,
                    'optical_meta' => $opticalMeta,
                ];
            }

            if ($totals['lens'] > 0 && $serviceId) {
                $lensLabel = 'Lens coating';
                if (! empty($cart['lens_coating_id'])) {
                    $lensRow = \App\Models\OpticalLensNoPrescription::query()->find($cart['lens_coating_id']);
                    $lensLabel = $lensRow?->name ? 'Lens: '.$lensRow->name : $lensLabel;
                }
                $prepared[] = [
                    'product_id' => $serviceId,
                    'size_option_id' => null,
                    'color_option_id' => null,
                    'line_label' => $lensLabel,
                    'quantity' => 1,
                    'price' => $totals['lens'],
                    'unit_cost' => 0,
                    'optical_meta' => ['route' => 'shop', 'lens_coating_id' => $cart['lens_coating_id'] ?? null],
                ];
            }

            $paymentStatus = $meta['payment_status'] ?? Order::PAYMENT_STATUS_UNPAID;
            $status = $meta['status'] ?? ($paymentStatus === Order::PAYMENT_STATUS_PAID ? 'completed' : 'processing');

            $order = Order::create([
                'customer_id' => $customer->id,
                'branch_id' => $branchId,
                'total_amount' => $totals['total'],
                'discount_amount' => 0,
                'discount_type' => 'fixed',
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'status' => $status,
                'source' => 'online',
                'external_ref' => $meta['external_ref'],
                'shipping_status' => 'pending',
                'amount_paid' => 0,
                'balance_due' => $totals['total'],
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            ]);

            foreach ($prepared as $row) {
                OrderItem::create(['order_id' => $order->id, ...$row]);
            }

            $paymentTypeId = config('storefront.payment_type_id');
            $paymentType = $paymentTypeId
                ? PaymentType::query()->whereKey($paymentTypeId)->first()
                : PaymentType::query()
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->where('name', 'like', '%Chapa%')->orWhere('name', 'like', '%Online%');
                    })
                    ->first();

            $payAmount = array_key_exists('payment_amount', $meta) && $meta['payment_amount'] !== null
                ? round((float) $meta['payment_amount'], 2)
                : ($paymentStatus === Order::PAYMENT_STATUS_PAID ? $totals['total'] : 0);

            if ($paymentType && $payAmount > 0) {
                Payment::create([
                    'order_id' => $order->id,
                    'branch_id' => $branchId,
                    'payment_type_id' => $paymentType->id,
                    'amount' => $payAmount,
                    'payment_method' => 'Chapa',
                    'status' => 'completed',
                ]);
            }

            $order->syncPaymentTotals();

            $batchToken = $cart['rx_batch_token'] ?? null;
            if (is_string($batchToken) && $batchToken !== '') {
                ShopPrescription::query()
                    ->where('batch_token', $batchToken)
                    ->whereNull('order_id')
                    ->update(['order_id' => $order->id]);
            }

            return $order->fresh(['orderItems', 'shopPrescriptions']);
        });
    }

    /**
     * @param  array<string, mixed>  $cart
     * @return array{subtotal:float,prescription:float,lens:float,total:float}
     */
    public function computeTotals(array $cart): array
    {
        $subtotal = 0.0;
        foreach ($cart['items'] ?? [] as $item) {
            $subtotal += (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }

        $prescription = ! empty($cart['prescription']['scanned'])
            ? (float) ($cart['prescription']['price'] ?? config('storefront.prescription_price', 0))
            : 0.0;

        $lens = 0.0;
        if (! empty($cart['lens_coating_id'])) {
            $price = OpticalLensNoPrescription::query()
                ->where('is_active', true)
                ->whereKey((int) $cart['lens_coating_id'])
                ->value('price');
            $lens = $price !== null ? (float) $price : 0.0;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'prescription' => round($prescription, 2),
            'lens' => round($lens, 2),
            'total' => round($subtotal + $prescription + $lens, 2),
        ];
    }

    /**
     * @param  array{name:string,phone?:?string,email?:?string,address?:?string}  $payload
     */
    protected function resolveCustomer(array $payload): Customer
    {
        $phone = filled($payload['phone'] ?? null) ? trim((string) $payload['phone']) : null;
        $email = filled($payload['email'] ?? null) ? strtolower(trim((string) $payload['email'])) : null;

        $customer = null;
        if ($phone) {
            $customer = Customer::query()->where('phone', $phone)->first();
        }
        if (! $customer && $email) {
            $customer = Customer::query()->where('email', $email)->first();
        }

        if ($customer) {
            $customer->fill([
                'name' => $payload['name'],
                'phone' => $phone ?: $customer->phone,
                'address' => $payload['address'] ?? $customer->address,
            ])->save();

            return $customer;
        }

        return Customer::create([
            'name' => $payload['name'],
            'phone' => $phone,
            'email' => $email ?: null,
            'address' => $payload['address'] ?? null,
        ]);
    }
}
