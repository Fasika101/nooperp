<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#1d4ed8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>@yield('title', config('storefront.brand_name', 'New Online Optics'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f8ff;
            --bg-elevated: #ffffff;
            --ink: #0f172a;
            --ink-soft: #64748b;
            --line: rgba(29, 78, 216, 0.12);
            --accent: #2563eb;
            --accent-2: #60a5fa;
            --accent-soft: #eff6ff;
            --accent-btn: #dbeafe;
            --accent-btn-text: #1e40af;
            --accent-border: #bfdbfe;
            --danger: #dc2626;
            --radius: 1.25rem;
            --shadow: 0 10px 30px rgba(29, 78, 216, 0.08);
            --safe-b: env(safe-area-inset-bottom, 0px);
            --safe-t: env(safe-area-inset-top, 0px);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body {
            margin: 0; padding: 0; min-height: 100%;
            background: var(--bg);
            color: var(--ink);
            font-family: Ubuntu, system-ui, sans-serif;
            font-size: 16px; line-height: 1.45;
            overscroll-behavior: none;
            touch-action: manipulation;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }
        button, input, textarea, select { font: inherit; }
        .shop-shell {
            min-height: 100dvh;
            max-width: 480px;
            margin: 0 auto;
            padding: calc(0.75rem + var(--safe-t)) 1rem calc(7.5rem + var(--safe-b));
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .shop-top {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.75rem; margin-bottom: 1.25rem;
        }
        .shop-brand {
            font-weight: 700; font-size: 1.05rem; letter-spacing: -0.02em;
            color: var(--accent);
        }
        .shop-brand span { color: var(--ink); font-weight: 500; }
        .cart-pill {
            display: inline-flex; align-items: center; gap: 0.35rem;
            border-radius: 999px;
            padding: 0.45rem 0.85rem; font-size: 0.8125rem; font-weight: 700;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
        }
        .cart-pill:active { transform: scale(0.96); }
        .flash { border-radius: 0.85rem; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.875rem; animation: fadeUp 0.35s ease; }
        .flash-ok { background: var(--accent-soft); color: #1e3a8a; border: 1px solid var(--accent-border); }
        .flash-err { background: #fee2e2; color: var(--danger); }
        .page-kicker {
            font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--accent-2); margin: 0 0 0.35rem;
        }
        .page-title {
            font-size: clamp(1.55rem, 5.5vw, 2rem); line-height: 1.2;
            letter-spacing: -0.02em; margin: 0 0 0.5rem; font-weight: 700;
            color: var(--ink);
        }
        .page-sub { color: var(--ink-soft); margin: 0 0 1.5rem; font-size: 0.95rem; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            width: 100%; border: 0; border-radius: 999px; padding: 0.95rem 1.25rem;
            font-weight: 700; cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease, opacity 0.15s ease;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary {
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
        }
        .btn-primary:hover { background: #bfdbfe; }
        .btn-accent {
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.12);
        }
        .btn-accent:hover { background: #bfdbfe; }
        .btn-ghost {
            background: #fff;
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
        }
        .btn-ghost:hover { background: var(--accent-soft); }
        .btn-sm { width: auto; padding: 0.55rem 0.9rem; font-size: 0.8125rem; }
        .btn:disabled, .btn[disabled] { opacity: 0.45; pointer-events: none; }
        .dock {
            position: fixed; left: 50%; transform: translateX(-50%); bottom: 0;
            width: min(480px, 100%);
            padding: 0.75rem 1rem calc(0.75rem + var(--safe-b));
            background: linear-gradient(to top, var(--bg) 72%, transparent);
            z-index: 40;
        }
        .field { margin-bottom: 0.85rem; }
        .field label {
            display: block; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em;
            text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.35rem;
        }
        .field input, .field textarea {
            width: 100%; border: 1px solid var(--line); background: var(--bg-elevated);
            border-radius: 0.9rem; padding: 0.85rem 1rem; color: var(--ink); outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .field input:focus, .field textarea:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .card {
            background: var(--bg-elevated); border-radius: var(--radius);
            box-shadow: var(--shadow); overflow: hidden; border: 1px solid rgba(255,255,255,0.8);
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pop {
            0% { transform: scale(0.96); }
            60% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
        .anim-in { animation: fadeUp 0.4s ease both; }
        .shop-main { flex: 1 1 auto; }
        .shop-footer {
            margin-top: 2rem;
            padding: 1.25rem 0 0.5rem;
            border-top: 1px solid var(--line);
            text-align: center;
            font-size: 0.75rem;
            color: var(--ink-soft);
            line-height: 1.6;
        }
        .shop-footer a {
            color: var(--accent-btn-text);
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .shop-footer .footer-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0.35rem;
        }
        @media (min-width: 481px) {
            body { background: #e8eefc; }
            .shop-shell {
                margin-top: 1.5rem; margin-bottom: 1.5rem; background: var(--bg);
                border-radius: 1.75rem; box-shadow: var(--shadow); min-height: calc(100dvh - 3rem);
            }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="shop-shell">
        <header class="shop-top">
            <a href="{{ route('shop.gender') }}" class="shop-brand">{{ config('storefront.brand_name', 'New Online Optics') }}</a>
            @if(($cartCount ?? 0) > 0)
                <a href="{{ route('shop.cart') }}" class="cart-pill">Cart · {{ $cartCount }}</a>
            @endif
        </header>

        @if(session('success'))
            <div class="flash flash-ok">{{ is_array(session('success')) ? implode(' ', \Illuminate\Support\Arr::flatten(session('success'))) : session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash flash-err">{{ is_array(session('error')) ? implode(' ', \Illuminate\Support\Arr::flatten(session('error'))) : session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="flash flash-err">{{ $errors->first() }}</div>
        @endif

        <div class="shop-main">
            @yield('content')
        </div>

        <footer class="shop-footer">
            <div class="footer-links">
                <a href="{{ config('storefront.privacy_url') }}" target="_blank" rel="noopener noreferrer">Privacy and Security</a>
            </div>
            <div>
                {{ config('storefront.brand_name', 'New Online Optics') }}
                &copy; {{ config('storefront.copyright_year', date('Y')) }}. All Rights Reserved
            </div>
        </footer>
    </div>
    @stack('scripts')
    <script>
        document.addEventListener('touchstart', function (e) {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
        document.addEventListener('gesturestart', function (e) { e.preventDefault(); }, { passive: false });
        var lastTouch = 0;
        document.addEventListener('touchend', function (e) {
            var now = Date.now();
            if (now - lastTouch <= 300) e.preventDefault();
            lastTouch = now;
        }, { passive: false });
    </script>
</body>
</html>
