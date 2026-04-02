<?php

use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TelegramBotAttachmentController;
use App\Http\Controllers\TelegramBotWebhookController;
use Illuminate\Support\Facades\Route;

Route::post(config('integrations.telegram_bot_webhook_path'), TelegramBotWebhookController::class)
    ->name('telegram.bot.webhook');

Route::get('/', function () {
    $ua = strtolower(request()->userAgent() ?? '');

    // Telegram's preview crawler often fails on large HTML (e.g. inline Tailwind fallback). Serve a tiny page with the same meta tags.
    if (str_contains($ua, 'telegram')) {
        return response()
            ->view('welcome-preview')
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    return view('welcome');
});

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/receipt/{order}', [ReceiptController::class, 'show'])->name('receipt.show');
    Route::get('/receipt/{order}/pdf', [ReceiptController::class, 'pdf'])->name('receipt.pdf');
    Route::get('/telegram-bot/messages/{message}/attachment', TelegramBotAttachmentController::class)
        ->name('telegram.bot.message.attachment');
});
