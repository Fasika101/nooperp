{{-- Minimal HTML for Telegram / link-preview crawlers (small response, no huge inline CSS). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.welcome-meta')
    </head>
    <body style="font-family: system-ui, sans-serif; margin: 1.5rem; color: #1b1b18; background: #fdfdfc;">
        <h1 style="font-size: 1.25rem; font-weight: 600;">{{ config('app.name', 'Liba Digitals') }} ERP</h1>
        <p style="max-width: 42rem; line-height: 1.5; color: #444;">
            Liba Digitals ERP: unified operations for retail and optical — sales, POS, stock, finance, and inventory.
            Sign in to manage your business.
        </p>
    </body>
</html>
