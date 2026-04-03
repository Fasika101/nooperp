<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TelegramBotChat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Throwable;

class PosTelegramService
{
    public function __construct(
        protected TelegramBotService $bots,
    ) {}

    public function resolveTelegramChat(Customer $customer): ?TelegramBotChat
    {
        if ($customer->telegram_bot_chat_id === null) {
            return null;
        }

        return $customer->telegramBotChat;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cart
     */
    public function sendCartPreviewToCustomer(Customer $customer, array $cart, string $currency): void
    {
        $chat = $this->resolveTelegramChat($customer);
        if (! $chat) {
            throw new \RuntimeException('This customer is not linked to Telegram. They should message your bot and complete registration first.');
        }

        if (! $this->bots->hasBotToken()) {
            throw new \RuntimeException('Telegram bot is not configured.');
        }

        if ($cart === []) {
            throw new \RuntimeException('Cart is empty.');
        }

        $this->bots->sendTextToChat($chat, 'Here are product options we selected for you from our store:');

        foreach ($cart as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $unit = (float) ($item['price'] ?? 0);
            $lineTotal = $unit * $qty;
            $caption = ($item['name'] ?? 'Item')."\n".Number::currency($lineTotal, $currency);
            if ($qty > 1) {
                $caption .= "\n(".$qty.' × '.Number::currency($unit, $currency).')';
            }

            $isOptical = ! empty($item['is_optical']);
            $image = $isOptical ? null : ($item['image'] ?? null);

            if (is_string($image) && $image !== '') {
                $absolute = Storage::disk('public')->path($image);
                if (is_readable($absolute)) {
                    $this->bots->sendAttachmentToChat(
                        $chat,
                        $absolute,
                        basename($image) ?: 'product.jpg',
                        $caption
                    );
                    usleep(200_000);

                    continue;
                }
            }

            $this->bots->sendTextToChat($chat, '📦 '.$caption);
            usleep(100_000);
        }

        $this->bots->sendTextToChat($chat, 'Reply here if you would like to order or need more options.');
    }

    public function sendOrderReceiptToCustomer(Customer $customer, Order $order): void
    {
        if (($customer->email ?? null) === 'walkin@pos.local') {
            return;
        }

        $chat = $this->resolveTelegramChat($customer);
        if (! $chat || ! $this->bots->hasBotToken()) {
            return;
        }

        $order->loadMissing(['orderItems.product', 'customer', 'taxType']);
        $currency = Setting::getDefaultCurrency();
        $forPdf = true;

        $tmp = tempnam(sys_get_temp_dir(), 'tg-rcpt');
        if ($tmp === false) {
            Log::warning('POS Telegram receipt: could not create temp file.');

            return;
        }

        $pdfPath = $tmp.'.pdf';

        try {
            Pdf::loadView('receipt', ['order' => $order, 'currency' => $currency, 'forPdf' => $forPdf])->save($pdfPath);
            @unlink($tmp);

            if (! is_readable($pdfPath)) {
                return;
            }

            $this->bots->sendAttachmentToChat(
                $chat,
                $pdfPath,
                "receipt-{$order->id}.pdf",
                'Thank you for your purchase! Receipt #'.$order->id.'.'
            );
        } catch (Throwable $e) {
            Log::warning('POS Telegram receipt PDF failed: '.$e->getMessage(), ['exception' => $e]);
            try {
                $this->bots->sendTextToChat($chat, $this->buildReceiptTextFallback($order, $currency));
            } catch (Throwable $e2) {
                Log::warning('POS Telegram receipt text fallback failed: '.$e2->getMessage(), ['exception' => $e2]);
            }
        } finally {
            if (is_file($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    protected function buildReceiptTextFallback(Order $order, string $currency): string
    {
        $lines = [
            'Receipt #'.$order->id,
            $order->created_at?->format('Y-m-d H:i') ?? '',
            'Total: '.Number::currency((float) $order->total_amount, $currency),
            'Thank you for your purchase!',
        ];

        return implode("\n", array_filter($lines));
    }
}
