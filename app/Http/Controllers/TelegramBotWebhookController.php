<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramBotWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBotService $bots): Response
    {
        $secret = Setting::getEncrypted('integrations_telegram_webhook_secret');
        if ($secret !== null && $secret !== '') {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (! hash_equals($secret, (string) $header)) {
                return response('Forbidden', 403);
            }
        }

        if (! $bots->hasBotToken()) {
            return response('Bot not configured', 503);
        }

        $payload = $request->all();
        if ($payload === []) {
            return response('OK', 200);
        }

        try {
            $bots->processWebhookUpdate($payload);
        } catch (\Throwable $e) {
            $bots->logWebhookError($e);
            // Still return 200 so Telegram does not retry indefinitely on app bugs
        }

        return response('OK', 200);
    }
}
