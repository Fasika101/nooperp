<?php

use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\ShopController;
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

Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'gender'])->name('gender');
    Route::get('/gender/{genderId}', [ShopController::class, 'selectGender'])->name('gender.select')->whereNumber('genderId');
    Route::get('/frames', [ShopController::class, 'frames'])->name('frames');
    Route::get('/search', [ShopController::class, 'search'])->name('search');
    Route::post('/cart/add', [ShopController::class, 'addToCart'])->name('cart.add');

    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::delete('/cart/item/{index}', [CartController::class, 'removeItem'])->name('cart.remove')->whereNumber('index');
    Route::post('/cart/prescription', [CartController::class, 'uploadPrescription'])->name('cart.prescription');
    Route::delete('/cart/prescription', [CartController::class, 'clearPrescription'])->name('cart.prescription.clear');
    Route::post('/cart/options', [CartController::class, 'updateOptions'])->name('cart.options');
    Route::post('/cart/continue', [CartController::class, 'continueToCheckout'])->name('cart.continue');
    Route::get('/checkout', [CartController::class, 'checkoutForm'])->name('checkout');
    Route::post('/checkout', [CartController::class, 'checkout'])->name('checkout.pay');
    Route::match(['get', 'post'], '/chapa/callback', [CartController::class, 'chapaCallback'])->name('chapa.callback');
    Route::get('/success', [CartController::class, 'success'])->name('success');
});

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/receipt/{order}', [ReceiptController::class, 'show'])->name('receipt.show');
    Route::get('/receipt/{order}/pdf', [ReceiptController::class, 'pdf'])->name('receipt.pdf');

    Route::get('/prescription/{prescription}/print', [PrescriptionController::class, 'print'])->name('prescription.print');
    Route::get('/prescription/{prescription}/pdf', [PrescriptionController::class, 'pdf'])->name('prescription.pdf');

    Route::get('/telegram-bot/messages/{message}/attachment', TelegramBotAttachmentController::class)
        ->name('telegram.bot.message.attachment');
});
