<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront / in-app shop (same ERP database)
    |--------------------------------------------------------------------------
    */

    'api_token' => env('STOREFRONT_API_TOKEN'),

    /** Branch used for online stock + order fulfillment */
    'branch_id' => (int) env('STOREFRONT_BRANCH_ID', 1),

    /** PaymentType id for online gateways (e.g. Chapa). */
    'payment_type_id' => env('STOREFRONT_PAYMENT_TYPE_ID') !== null && env('STOREFRONT_PAYMENT_TYPE_ID') !== ''
        ? (int) env('STOREFRONT_PAYMENT_TYPE_ID')
        : null,

    'chapa' => [
        'secret_key' => env('CHAPA_SECRET_KEY'),
        'public_key' => env('CHAPA_PUBLIC_KEY'),
        'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
    ],

    /** Flat add-on when a prescription photo is uploaded / scanned */
    'prescription_price' => (float) env('SHOP_PRESCRIPTION_PRICE', 800),

    /**
     * Optional explicit IDs from optical_lens_no_prescriptions.
     * If null, auto-matched by name (BLUE → Blue cut, ANTI without BLUE → Anti glare).
     */
    'lens_blue_cut_id' => env('SHOP_LENS_BLUE_CUT_ID') !== null && env('SHOP_LENS_BLUE_CUT_ID') !== ''
        ? (int) env('SHOP_LENS_BLUE_CUT_ID')
        : null,
    'lens_anti_glare_id' => env('SHOP_LENS_ANTI_GLARE_ID') !== null && env('SHOP_LENS_ANTI_GLARE_ID') !== ''
        ? (int) env('SHOP_LENS_ANTI_GLARE_ID')
        : null,

    'currency' => env('SHOP_CURRENCY', 'ETB'),

    'brand_name' => env('SHOP_BRAND_NAME', 'New Online Optics'),

    'privacy_url' => env('SHOP_PRIVACY_URL', 'https://newonlineoptics.com/blog-single.php?slug=newoptics-privacy'),

    'copyright_year' => (int) env('SHOP_COPYRIGHT_YEAR', date('Y')),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    /*
    | Banuba TINT virtual try-on (glasses).
    | Get merchant ID via https://www.banuba.com form or info@banuba.com
    | Free trial / Shopify free plan — custom storefront uses merchant-id from Banuba.
    */
    'banuba' => [
        'tint_merchant_id' => env('BANUBA_TINT_MERCHANT_ID'),
        'client_token' => env('BANUBA_CLIENT_TOKEN'), // optional / legacy; merchant-id preferred
        'widget_url' => env('BANUBA_TINT_WIDGET_URL', 'https://tintvto.com/widget.js'),
    ],
];
