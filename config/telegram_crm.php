<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Optical / product keywords (for CRM reports)
    |--------------------------------------------------------------------------
    | Matched case-insensitively against imported message text.
    */
    'product_keywords' => [
        'sunglasses',
        'sun glasses',
        'prescription',
        'progressive',
        'bifocal',
        'single vision',
        'reading glasses',
        'frame',
        'frames',
        'lens',
        'lenses',
        'anti-reflect',
        'blue light',
        'contact lens',
        'optical',
        'RX',
        'sphere',
        'cylinder',
        'axis',
        'PD',
    ],

    /*
    |--------------------------------------------------------------------------
    | Address / location hints (SQL LIKE, case-insensitive via LOWER)
    |--------------------------------------------------------------------------
    | Substrings to match in message text. Tune for your region.
    */
    'address_keywords' => [
        'woreda',
        'kebele',
        'addis',
        'ethiopia',
        'street',
        ' road ',
        'avenue',
        'location',
        'near ',
        'bole',
        'piassa',
        'piazza',
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone number extraction (Telegram → customer sync)
    |--------------------------------------------------------------------------
    | Regex patterns matched against message text. Tuned for Ethiopia + common
    | international formats. Adjust as needed.
    */
    'phone_patterns' => [
        '/\+?251[79]\d{8}/',
        '/09\d{8}/',
        '/07\d{8}/',
    ],

];
