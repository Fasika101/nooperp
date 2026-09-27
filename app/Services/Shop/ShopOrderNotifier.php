<?php

namespace App\Services\Shop;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Services\PosTelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopOrderNotifier
{
    public function __construct(
        protected PosTelegramService $telegram,
        protected SmsEthiopiaService $sms,
    ) {}

    /**
     * Send ERP receipt via Telegram (if linked) and short SMS (ref + amount).
     * Safe to call more than once — only runs once per order.
     */
    public function notifyPaid(Order $order): void
    {
        $lockKey = 'shop_order_notified_'.$order->id;
        if (! Cache::add($lockKey, 1, now()->addDays(30))) {
            return;
        }

        $order->loadMissing(['customer', 'orderItems.product']);

        try {
            $this->sendTelegramReceipt($order);
        } catch (\Throwable $e) {
            Log::warning('Shop Telegram receipt: '.$e->getMessage(), ['order_id' => $order->id]);
        }

        try {
            $this->sendSmsSummary($order);
        } catch (\Throwable $e) {
            Log::warning('Shop SMS receipt: '.$e->getMessage(), ['order_id' => $order->id]);
        }
    }

    protected function sendTelegramReceipt(Order $order): void
    {
        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        // Prefer this customer's chat; otherwise find another profile with same phone that has Telegram.
        if (! $customer->telegram_bot_chat_id && $customer->phone) {
            $digits = preg_replace('/\D+/', '', $customer->phone) ?? '';
            $alt = Customer::query()
                ->whereNotNull('telegram_bot_chat_id')
                ->where(function ($q) use ($customer, $digits) {
                    $q->where('phone', $customer->phone);
                    if ($digits !== '') {
                        $q->orWhere('phone', '+'.$digits)
                            ->orWhere('phone', $digits)
                            ->orWhere('phone', '0'.substr($digits, -9));
                    }
                })
                ->orderBy('id')
                ->first();

            if ($alt?->telegram_bot_chat_id) {
                // Use alt customer for send only (unique FK — don't steal the chat link).
                $this->telegram->sendOrderReceiptToCustomer($alt, $order);

                return;
            }
        }

        $this->telegram->sendOrderReceiptToCustomer($customer, $order);
    }

    protected function sendSmsSummary(Order $order): void
    {
        $phone = $order->customer?->phone;
        if (! $phone || ! $this->sms->isConfigured()) {
            return;
        }

        $currency = Setting::getDefaultCurrency();
        $ref = $order->external_ref ?: ('#'.$order->id);
        $amount = number_format((float) $order->total_amount, 2);
        $text = "New Online Optics: Payment OK. Ref {$ref}. Amount {$currency} {$amount}.";

        $result = $this->sms->send($phone, $text);
        if (! ($result['ok'] ?? false)) {
            Log::info('Shop SMS not sent: '.($result['error'] ?? 'unknown'), ['order_id' => $order->id]);
        }
    }
}
