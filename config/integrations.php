<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot webhook path (relative to site URL)
    |--------------------------------------------------------------------------
    | Full URL shown on Settings → Integrations. Register with Telegram:
    | POST https://api.telegram.org/bot<token>/setWebhook?url=<url>
    */
    'telegram_bot_webhook_path' => env('TELEGRAM_BOT_WEBHOOK_PATH', 'telegram/bot/webhook'),

];
