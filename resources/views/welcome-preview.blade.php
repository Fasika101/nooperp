{{-- Minimal HTML for Telegram / link-preview crawlers (small response, no huge inline CSS). --}}
@php
    use App\Support\PublicLanding;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.welcome-meta')
    </head>
    <body style="font-family: system-ui, sans-serif; margin: 1.5rem; color: #1b1b18; background: #fdfdfc;">
        <h1 style="font-size: 1.25rem; font-weight: 600;">{{ PublicLanding::metaTitle() }}</h1>
        <p style="max-width: 42rem; line-height: 1.5; color: #444;">
            {{ PublicLanding::metaDescription() }}
        </p>
    </body>
</html>
