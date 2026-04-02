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

    /*
    | Collect name, phone, email, and address in private chats (/start, /register).
    */
    'telegram_customer_onboarding_enabled' => env('TELEGRAM_CUSTOMER_ONBOARDING_ENABLED', true),

    'telegram_onboarding_intro' => env(
        'TELEGRAM_ONBOARDING_INTRO',
        'We will save your contact details as a customer profile so we can assist you.'
    ),

    'telegram_onboarding_ask_name' => env(
        'TELEGRAM_ONBOARDING_ASK_NAME',
        'Please send your full name (2–120 characters).'
    ),

    'telegram_onboarding_ask_phone' => env(
        'TELEGRAM_ONBOARDING_ASK_PHONE',
        'Please share your phone number with the button below, or type your number (including country code if possible).'
    ),

    'telegram_onboarding_share_phone_button' => env(
        'TELEGRAM_ONBOARDING_SHARE_PHONE_BUTTON',
        'Share phone number'
    ),

    'telegram_onboarding_ask_email' => env(
        'TELEGRAM_ONBOARDING_ASK_EMAIL',
        'Thanks! Now send your email address.'
    ),

    'telegram_onboarding_ask_address' => env(
        'TELEGRAM_ONBOARDING_ASK_ADDRESS',
        'Almost done. Please send your address (delivery or contact).'
    ),

    'telegram_onboarding_unknown_command' => env(
        'TELEGRAM_ONBOARDING_UNKNOWN_COMMAND',
        'Please finish this step with a normal message, or send /cancel. To restart, send /register.'
    ),

    'telegram_onboarding_complete' => env(
        'TELEGRAM_ONBOARDING_COMPLETE',
        'Your details have been saved. Thank you! (Customer #:id)'
    ),

    'telegram_onboarding_cancelled' => env(
        'TELEGRAM_ONBOARDING_CANCELLED',
        'Registration cancelled. Send /register anytime to start again.'
    ),

    'telegram_onboarding_need_text' => env(
        'TELEGRAM_ONBOARDING_NEED_TEXT',
        'Please send text for this step (or use /cancel).'
    ),

    'telegram_onboarding_invalid_name' => env(
        'TELEGRAM_ONBOARDING_INVALID_NAME',
        'That name looks invalid. Please send your full name (2–120 characters).'
    ),

    'telegram_onboarding_invalid_phone' => env(
        'TELEGRAM_ONBOARDING_INVALID_PHONE',
        'Invalid phone number. Share your contact or type a number with at least 8 digits.'
    ),

    'telegram_onboarding_invalid_contact' => env(
        'TELEGRAM_ONBOARDING_INVALID_CONTACT',
        'Please share your own phone number using the button, not someone else\'s contact.'
    ),

    'telegram_onboarding_invalid_email' => env(
        'TELEGRAM_ONBOARDING_INVALID_EMAIL',
        'That email doesn\'t look valid. Please send a valid email address.'
    ),

    'telegram_onboarding_invalid_address' => env(
        'TELEGRAM_ONBOARDING_INVALID_ADDRESS',
        'Please send a clearer address (3–500 characters).'
    ),

];
