<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot webhook path (relative to site URL)
    |--------------------------------------------------------------------------
    | Full URL shown on Settings → Integrations. Register with Telegram:
    | POST https://api.telegram.org/bot<token>/setWebhook?url=<url>
    |
    | If you override TELEGRAM_BOT_WEBHOOK_PATH, add the same path to the
    | validateCsrfTokens except list in bootstrap/app.php.
    */
    'telegram_bot_webhook_path' => env('TELEGRAM_BOT_WEBHOOK_PATH', 'telegram/bot/webhook'),

    /*
    |--------------------------------------------------------------------------
    | Optional env fallbacks (production / Docker)
    |--------------------------------------------------------------------------
    | Values in Admin → Settings → Integrations take priority. If the token is
    | only in .env, it will still work.
    */
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'telegram_webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
    | Reply sent when user sends /start (so the bot appears "alive" in Telegram).
    */
    'telegram_welcome_message' => env(
        'TELEGRAM_WELCOME_MESSAGE',
        'Hello! Your message was received. Our team can see this chat in the system and will reply here when possible.'
    ),

];
