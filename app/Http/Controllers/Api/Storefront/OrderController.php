<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BranchProductStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_ref' => ['required', 'string', 'max:191'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.address' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.color_option_id' => ['nullable', 'integer'],
            'items.*.size_option_id' => ['nullable', 'integer'],
            'items.*.line_label' => ['nullable', 'string', 'max:500'],
            'items.*.optical_meta' => ['nullable', 'array'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'in:paid,unpaid,partial'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_type_id' => ['nullable', 'integer', 'exists:payment_types,id'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,processing,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $branchId = (int) config('storefront.branch_id');
        if ($branchId < 1) {
            return response()->json(['message' => 'STOREFRONT_BRANCH_ID is not configured'], 503);
        }

        // Idempotent: same external_ref returns existing order
        $existing = Order::query()->where('external_ref', $data['external_ref'])->first();
        if ($existing) {
            return response()->json([
                'message' => 'Order already exists',
                'data' => [
                    'order_id' => $existing->id,
                    'external_ref' => $existing->external_ref,
                    'status' => $existing->status,
                    'payment_status' => $existing->payment_status,
                    'total_amount' => (float) $existing->total_amount,
                ],
            ]);
        }

        try {
            $order = DB::transaction(function () use ($data, $branchId) {
                $customer = $this->resolveCustomer($data['customer']);

                $lineTotal = 0.0;
                $preparedItems = [];

                foreach ($data['items'] as $item) {
                    $product = Product::query()->whereKey($item['product_id'])->lockForUpdate()->first();
                    if (! $product || $product->is_service) {
                        throw ValidationException::withMessages([
                            'items' => 'Invalid product id '.$item['product_id'],
                        ]);
                    }

                    $colorOptionId = isset($item['color_option_id']) ? (int) $item['color_option_id'] : null;
                    if ($colorOptionId !== null && $colorOptionId <= 0) {
                        $colorOptionId = null;
                    }
                    $sizeOptionId = isset($item['size_option_id']) ? (int) $item['size_option_id'] : null;
                    if ($sizeOptionId !== null && $sizeOptionId <= 0) {
                        $sizeOptionId = null;
                    }

                    $qty = (int) $item['quantity'];
                    $unitPrice = array_key_exists('price', $item) && $item['price'] !== null
                        ? round((float) $item['price'], 2)
                        : round((float) $product->price, 2);

                    // Skip stock for pure optical/service-style lines without variant
                    $deductStock = empty($item['optical_meta']) || $product->is_service === false;

                    if ($deductStock && ! $product->is_service) {
                        $variant = ProductVariant::query()
                            ->where('product_id', $product->id)
                            ->when(
                                $colorOptionId !== null,
                                fn ($q) => $q->where('color_option_id', $colorOptionId),
                                fn ($q) => $q->whereNull('color_option_id'),
                            )
                            ->when(
                                $sizeOptionId !== null,
                                fn ($q) => $q->where('size_option_id', $sizeOptionId),
                                fn ($q) => $q->whereNull('size_option_id'),
                            )
                            ->first();

                        if (! $variant) {
                            $variant = ProductVariant::findOrCreateForProduct($product->id, $colorOptionId, $sizeOptionId);
                        }

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
                                'items' => "Insufficient stock for {$product->name}",
                            ]);
                        }

                        $branchStock->decrement('quantity', $qty);
                    }

                    $lineLabel = $item['line_label']
                        ?? $product->formatNameWithVariant($sizeOptionId, $colorOptionId);

                    $preparedItems[] = [
                        'product_id' => $product->id,
                        'size_option_id' => $sizeOptionId,
                        'color_option_id' => $colorOptionId,
                        'line_label' => $lineLabel,
                        'quantity' => $qty,
                        'price' => $unitPrice,
                        'unit_cost' => (float) ($product->cost_price ?? 0),
                        'optical_meta' => $item['optical_meta'] ?? null,
                    ];

                    $lineTotal += $unitPrice * $qty;
                }

                $shipping = round((float) ($data['shipping_amount'] ?? 0), 2);
                $discount = round((float) ($data['discount_amount'] ?? 0), 2);
                $tax = round((float) ($data['tax_amount'] ?? 0), 2);
                $total = round(max(0, $lineTotal + $shipping + $tax - $discount), 2);

                $paymentStatus = $data['payment_status'] ?? 'paid';
                $status = $data['status'] ?? ($paymentStatus === 'paid' ? 'completed' : 'processing');

                $order = Order::create([
                    'customer_id' => $customer->id,
                    'branch_id' => $branchId,
                    'total_amount' => $total,
                    'discount_amount' => $discount,
                    'discount_type' => 'fixed',
                    'shipping_amount' => $shipping,
                    'tax_amount' => $tax,
                    'status' => $status,
                    'source' => 'online',
                    'external_ref' => $data['external_ref'],
                    'amount_paid' => 0,
                    'balance_due' => $total,
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                ]);

                foreach ($preparedItems as $row) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        ...$row,
                    ]);
                }

                $paymentTypeId = $data['payment_type_id']
                    ?? config('storefront.payment_type_id');

                if ($paymentTypeId) {
                    $paymentType = PaymentType::query()->whereKey($paymentTypeId)->first();
                } else {
                    $paymentType = PaymentType::query()
                        ->where('is_active', true)
                        ->where(function ($q) {
                            $q->where('name', 'like', '%Chapa%')
                                ->orWhere('name', 'like', '%Online%');
                        })
                        ->first();
                }

                $payAmount = array_key_exists('payment_amount', $data) && $data['payment_amount'] !== null
                    ? round((float) $data['payment_amount'], 2)
                    : ($paymentStatus === 'paid' ? $total : 0.0);

                if ($paymentType && $payAmount > 0) {
                    Payment::create([
                        'order_id' => $order->id,
                        'branch_id' => $branchId,
                        'payment_type_id' => $paymentType->id,
                        'amount' => $payAmount,
                        'payment_method' => $data['payment_method'] ?? $paymentType->name,
                        'status' => 'completed',
                    ]);
                }

                $order->syncPaymentTotals();

                return $order->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Order created',
            'data' => [
                'order_id' => $order->id,
                'external_ref' => $order->external_ref,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'amount_paid' => (float) $order->amount_paid,
                'balance_due' => (float) $order->balance_due,
            ],
        ], 201);
    }

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string, address?: ?string}  $payload
     */
    protected function resolveCustomer(array $payload): Customer
    {
        $email = filled($payload['email'] ?? null) ? strtolower(trim((string) $payload['email'])) : null;
        $phone = filled($payload['phone'] ?? null) ? trim((string) $payload['phone']) : null;

        $customer = null;
        if ($email) {
            $customer = Customer::query()->where('email', $email)->first();
        }
        if (! $customer && $phone) {
            $customer = Customer::query()->where('phone', $phone)->first();
        }

        if ($customer) {
            $customer->fill([
                'name' => $payload['name'],
                'phone' => $phone ?: $customer->phone,
                'email' => $email ?: $customer->email,
                'address' => $payload['address'] ?? $customer->address,
            ])->save();

            return $customer;
        }

        return Customer::create([
            'name' => $payload['name'],
            'phone' => $phone,
            'email' => $email,
            'address' => $payload['address'] ?? null,
        ]);
    }
}
