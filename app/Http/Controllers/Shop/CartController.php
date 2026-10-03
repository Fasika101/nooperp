<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShopPrescription;
use App\Services\Shop\ChapaService;
use App\Services\Shop\GeminiPrescriptionScanner;
use App\Services\Shop\ShopCart;
use App\Services\Shop\ShopOrderNotifier;
use App\Services\Shop\ShopOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        protected ShopOrderService $orders,
        protected ChapaService $chapa,
        protected GeminiPrescriptionScanner $scanner,
    ) {}

    public function show(): View|RedirectResponse
    {
        $cart = ShopCart::get();
        if (empty($cart['gender_id'])) {
            return redirect()->route('shop.gender');
        }
        if (empty($cart['items'])) {
            return redirect()->route('shop.frames')->with('error', 'Your cart is empty. Pick a frame first.');
        }

        $rxPrice = ! empty($cart['prescription']['price'])
            ? (float) $cart['prescription']['price']
            : (float) config('storefront.prescription_price', 0);

        return view('shop.cart', [
            'cart' => $cart,
            'totals' => ShopCart::totals(),
            'lensCoatings' => app(\App\Services\Shop\ShopCatalogService::class)->lensCoatings(),
            'prescriptionPrice' => $rxPrice,
            'geminiReady' => $this->scanner->isConfigured(),
            'brand' => config('storefront.brand_name'),
            'currency' => config('storefront.currency', 'ETB'),
            'cartCount' => ShopCart::totals()['item_count'],
            'stickyNav' => true,
            'backUrl' => route('shop.frames'),
            'backLabel' => 'Back',
            'shopStep' => 3,
            'hasDock' => true,
        ]);
    }

    public function continueToCheckout(Request $request): RedirectResponse
    {
        $cart = ShopCart::get();
        if (empty($cart['items'])) {
            return redirect()->route('shop.frames')->with('error', 'Your cart is empty. Pick a frame first.');
        }

        $data = $request->validate([
            'lens_coating_id' => ['nullable', 'integer'],
        ]);

        $coatingId = isset($data['lens_coating_id']) ? (int) $data['lens_coating_id'] : null;
        if ($coatingId !== null && $coatingId <= 0) {
            $coatingId = null;
        }
        ShopCart::setLensCoating($coatingId);

        return redirect()->route('shop.checkout');
    }

    public function checkoutForm(): View|RedirectResponse
    {
        $cart = ShopCart::get();
        if (empty($cart['gender_id'])) {
            return redirect()->route('shop.gender');
        }
        if (empty($cart['items'])) {
            return redirect()->route('shop.frames')->with('error', 'Your cart is empty. Pick a frame first.');
        }

        return view('shop.checkout', [
            'cart' => $cart,
            'totals' => ShopCart::totals(),
            'brand' => config('storefront.brand_name'),
            'currency' => config('storefront.currency', 'ETB'),
            'cartCount' => ShopCart::totals()['item_count'],
            'chapaReady' => $this->chapa->isConfigured(),
            'stickyNav' => true,
            'backUrl' => route('shop.cart'),
            'backLabel' => 'Back',
            'shopStep' => 4,
            'hasDock' => true,
        ]);
    }

    public function removeItem(int $index): RedirectResponse
    {
        ShopCart::removeItem($index);

        if (ShopCart::totals()['item_count'] < 1) {
            return redirect()->route('shop.frames');
        }

        return redirect()->route('shop.cart');
    }

    public function uploadPrescription(Request $request): RedirectResponse
    {
        $request->validate([
            'prescription' => ['required', 'image', 'max:8192'],
        ]);

        $file = $request->file('prescription');

        try {
            $scan = $this->scanner->scan($file);
            $quote = $this->scanner->quoteFromScan($scan);
            $price = (float) ($quote['price'] ?? 0);
            if (($quote['mode'] ?? '') === 'quote' || $price < 0) {
                $price = 0.0;
            }
            $scan['pricing'] = $quote;
        } catch (\Throwable $e) {
            return redirect()->route('shop.cart')->with('error', $e->getMessage());
        }

        $batch = ShopCart::ensureRxBatchToken();
        $path = $file->store('shop/prescriptions/'.$batch, 'public');

        $record = ShopPrescription::create([
            'batch_token' => $batch,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'scan_json' => $scan,
            'vision_type' => $scan['vision_type'] ?? null,
            'confidence' => $scan['confidence'] ?? null,
            'prescription_price' => $price,
            'scan_notes' => $scan['notes'] ?? null,
            'scan_ok' => true,
        ]);

        ShopCart::setPrescription($path, $scan, $price, $record->id, true, $quote);

        $msg = match ($quote['mode'] ?? 'exact') {
            'range' => 'Prescription scanned — estimated lens price range applied.',
            'quote' => 'Prescription scanned — lens price needs lab confirmation.',
            default => 'Prescription scanned — lens price updated from ERP tiers.',
        };

        return redirect()->route('shop.cart')->with('success', $msg);
    }

    public function clearPrescription(): RedirectResponse
    {
        $cart = ShopCart::get();
        $token = $cart['rx_batch_token'] ?? null;

        if ($token) {
            $files = ShopPrescription::query()->where('batch_token', $token)->whereNull('order_id')->get();
            foreach ($files as $rx) {
                if ($rx->path) {
                    Storage::disk('public')->delete($rx->path);
                }
                $rx->delete();
            }
        } elseif (! empty($cart['prescription']['path'])) {
            Storage::disk('public')->delete($cart['prescription']['path']);
        }

        ShopCart::clearPrescription();
        $cart = ShopCart::get();
        $cart['rx_batch_token'] = null;
        ShopCart::put($cart);

        return redirect()->route('shop.cart');
    }

    public function updateOptions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+251[79]\d{8}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'lens_coating_id' => ['nullable', 'integer'],
        ], [
            'phone.regex' => 'Phone must be a valid Ethiopian mobile (+251 followed by 9 digits).',
        ]);

        $coatingId = isset($data['lens_coating_id']) ? (int) $data['lens_coating_id'] : null;
        if ($coatingId !== null && $coatingId <= 0) {
            $coatingId = null;
        }
        ShopCart::setLensCoating($coatingId);

        ShopCart::setCustomer(
            (string) ($data['name'] ?? ''),
            $this->normalizeEthiopianPhone((string) ($data['phone'] ?? '')),
            (string) ($data['address'] ?? ''),
        );

        return redirect()->route('shop.cart');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+251[79]\d{8}$/'],
            'address' => ['required', 'string', 'max:1000'],
        ], [
            'phone.regex' => 'Enter a valid Ethiopian mobile: +251 and 9 digits starting with 9 or 7.',
        ]);

        if (! $this->chapa->isConfigured()) {
            return redirect()->route('shop.checkout')->with(
                'error',
                'Online payment is not ready yet. Add CHAPA_SECRET_KEY (and CHAPA_PUBLIC_KEY) to .env.'
            );
        }

        $phone = $this->normalizeEthiopianPhone($data['phone']);
        ShopCart::setCustomer($data['name'], $phone, $data['address']);

        $cart = ShopCart::get();
        $totals = ShopCart::totals();

        if ($totals['item_count'] < 1 || $totals['total'] <= 0) {
            return redirect()->route('shop.checkout')->with('error', 'Cart total is invalid.');
        }

        try {
            $this->orders->computeTotals($cart);
        } catch (\Throwable $e) {
            return redirect()->route('shop.checkout')->with('error', $e->getMessage());
        }

        $txRef = $this->chapa->makeTxRef();
        session(['shop_pending_tx' => $txRef]);

        Storage::disk('local')->put(
            'shop/pending/'.$txRef.'.json',
            json_encode(['cart' => $cart, 'totals' => $totals, 'created_at' => now()->toIso8601String()])
        );

        $init = $this->chapa->initialize(
            $totals['total'],
            $cart['customer'],
            $txRef,
            route('shop.chapa.callback'),
            route('shop.success', ['ref' => $txRef]),
        );

        // Rare race: if Chapa still says the ref was used, mint a fresh one and retry once.
        if ((! $init['ok'] || ! $init['checkout_url']) && $this->chapa->errorLooksLikeDuplicateRef($init['error'] ?? null)) {
            Storage::disk('local')->delete('shop/pending/'.$txRef.'.json');
            $txRef = $this->chapa->makeTxRef();
            session(['shop_pending_tx' => $txRef]);
            Storage::disk('local')->put(
                'shop/pending/'.$txRef.'.json',
                json_encode(['cart' => $cart, 'totals' => $totals, 'created_at' => now()->toIso8601String()])
            );
            $init = $this->chapa->initialize(
                $totals['total'],
                $cart['customer'],
                $txRef,
                route('shop.chapa.callback'),
                route('shop.success', ['ref' => $txRef]),
            );
        }

        if (! $init['ok'] || ! $init['checkout_url']) {
            Storage::disk('local')->delete('shop/pending/'.$txRef.'.json');
            $err = $init['error'] ?? 'Could not start Chapa payment.';
            if (is_array($err)) {
                $err = implode(' ', \Illuminate\Support\Arr::flatten($err));
            }

            return redirect()->route('shop.checkout')->with('error', (string) $err);
        }

        return redirect()->away($init['checkout_url']);
    }

    public function chapaCallback(Request $request): Response
    {
        $txRef = (string) ($request->input('trx_ref') ?: $request->input('tx_ref') ?: '');
        if ($txRef === '') {
            return response('missing transaction reference', 400);
        }

        $verify = $this->chapa->verify($txRef);
        if (! ($verify['verified'] ?? false)) {
            return response('not verified', 200);
        }

        $order = $this->finalizePaidOrder($txRef, $verify);
        if (! $order) {
            return response('order failed', 500);
        }

        return response('ok', 200);
    }

    public function success(Request $request): View
    {
        $ref = (string) $request->query('ref', '');
        $order = null;
        $paid = false;
        $verify = null;

        if ($ref !== '') {
            $order = Order::query()
                ->where('external_ref', $ref)
                ->with(['orderItems', 'customer'])
                ->first();

            if (! $order && Storage::disk('local')->exists('shop/pending/'.$ref.'.json')) {
                $verify = $this->chapa->verify($ref);
                if ($verify['verified'] ?? false) {
                    $order = $this->finalizePaidOrder($ref, $verify);
                }
            } elseif ($order && $order->payment_status === Order::PAYMENT_STATUS_PAID) {
                // Backfill Chapa reference if an older paid order is missing it
                if (blank($order->chapa_reference)) {
                    $verify = $this->chapa->verify($ref);
                    if (! empty($verify['reference'])) {
                        $order->forceFill(['chapa_reference' => $verify['reference']])->saveQuietly();
                    }
                }
                $paid = true;
                app(ShopOrderNotifier::class)->notifyPaid($order);
            }
        }

        if ($order && $order->payment_status === Order::PAYMENT_STATUS_PAID) {
            $paid = true;
            ShopCart::clear();
        }

        return view('shop.success', [
            'order' => $order,
            'ref' => $ref,
            'paid' => $paid,
            'chapaReference' => $order?->chapa_reference ?? ($verify['reference'] ?? null),
            'brand' => config('storefront.brand_name'),
            'currency' => config('storefront.currency', 'ETB'),
            'shopStep' => 5,
            'cartCount' => 0,
            'hasDock' => true,
        ]);
    }

    /**
     * Create the ERP order after Chapa verifies payment (idempotent by external_ref).
     *
     * @param  array<string, mixed>|null  $verify
     */
    protected function finalizePaidOrder(string $txRef, ?array $verify = null): ?Order
    {
        if ($verify === null) {
            $verify = $this->chapa->verify($txRef);
            if (! ($verify['verified'] ?? false)) {
                return Order::query()->where('external_ref', $txRef)->first();
            }
        }

        $chapaReference = isset($verify['reference']) && is_string($verify['reference']) && $verify['reference'] !== ''
            ? $verify['reference']
            : null;

        $existing = Order::query()->where('external_ref', $txRef)->first();
        if ($existing) {
            if ($chapaReference && blank($existing->chapa_reference)) {
                $existing->forceFill(['chapa_reference' => $chapaReference])->saveQuietly();
            }
            app(ShopOrderNotifier::class)->notifyPaid($existing);

            return $existing->fresh();
        }

        $path = 'shop/pending/'.$txRef.'.json';
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $payload = json_decode(Storage::disk('local')->get($path), true) ?: [];
        $cart = $payload['cart'] ?? null;
        if (! is_array($cart)) {
            return null;
        }

        $paymentAmount = (float) (($payload['totals']['total'] ?? 0));
        if (isset($verify['amount']) && is_numeric($verify['amount']) && (float) $verify['amount'] > 0) {
            $paymentAmount = (float) $verify['amount'];
        }

        try {
            $order = $this->orders->createFromCart($cart, [
                'external_ref' => $txRef,
                'chapa_reference' => $chapaReference,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_amount' => $paymentAmount,
                'status' => 'completed',
            ]);
            Storage::disk('local')->delete($path);
            app(ShopOrderNotifier::class)->notifyPaid($order);

            return $order;
        } catch (\Throwable $e) {
            Log::error('Shop finalize order failed: '.$e->getMessage(), [
                'tx' => $txRef,
                'chapa_reference' => $chapaReference,
            ]);

            return null;
        }
    }

    /**
     * Normalize to +251XXXXXXXXX (9 national digits after country code).
     */
    protected function normalizeEthiopianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '251') && strlen($digits) >= 12) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $digits = substr($digits, 1);
        }

        $digits = substr($digits, 0, 9);

        if (strlen($digits) !== 9 || ! in_array($digits[0], ['7', '9'], true)) {
            return $phone;
        }

        return '+251'.$digits;
    }
}
