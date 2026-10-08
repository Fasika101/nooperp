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
            padding: calc(0.75rem + var(--safe-t)) 1rem calc(1rem + var(--safe-b));
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .shop-shell.has-dock {
            padding-bottom: calc(0.5rem + var(--safe-b));
        }
        .shop-top {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.75rem; margin-bottom: 1rem;
        }
        .shop-top.is-sticky {
            position: sticky;
            top: 0;
            z-index: 50;
            margin: calc(-0.75rem - var(--safe-t)) -1rem 1rem;
            padding: calc(0.65rem + var(--safe-t)) 1rem 0.65rem;
            background: rgba(244, 248, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }
        .shop-nav-left {
            display: flex; align-items: center; gap: 0.55rem; min-width: 0; flex: 1;
        }
        .shop-back {
            flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 0.25rem;
            min-height: 2.5rem; min-width: 2.5rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
            font-size: 0.8125rem; font-weight: 700;
            transition: transform 0.12s ease, background 0.12s ease;
            touch-action: manipulation;
            cursor: pointer;
        }
        .shop-back:active { transform: scale(0.95); background: #bfdbfe; }
        .shop-brand {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-weight: 700; font-size: 1.05rem; letter-spacing: -0.02em;
            color: var(--accent);
            min-width: 0;
            overflow: hidden;
        }
        .shop-brand-logo {
            width: 2rem; height: 2rem; object-fit: contain; flex-shrink: 0;
            border-radius: 0.45rem; background: #fff;
        }
        .shop-brand-text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .shop-brand span { color: var(--ink); font-weight: 500; }
        a, button, .gender-card, .cart-pill, .shop-back, .btn {
            touch-action: manipulation;
            cursor: pointer;
            -webkit-user-select: none;
            user-select: none;
        }
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
        .page-sub { color: var(--ink-soft); margin: 0 0 1rem; font-size: 0.95rem; }
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
            margin-top: 1rem;
            padding: 0.75rem 0 0;
            text-align: center;
            font-size: 0.7rem;
            color: var(--ink-soft);
            line-height: 1.4;
        }
        .shop-progress {
            margin: 0 0 1rem;
            padding: 0.1rem 0 0.2rem;
        }
        .shop-progress-track {
            position: relative;
            height: 4px;
            margin: 0 1.1rem 0.7rem;
            border-radius: 999px;
            background: #dbeafe;
            overflow: hidden;
        }
        .shop-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #60a5fa, #2563eb);
            transition: width 0.35s ease;
        }
        .shop-progress-steps {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: space-between;
            gap: 0.25rem;
        }
        .shop-progress-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
            min-width: 0;
            color: #94a3b8;
        }
        .shop-progress-dot {
            width: 2.15rem;
            height: 2.15rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 1.5px solid #cbd5e1;
            color: #94a3b8;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }
        .shop-progress-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .shop-progress-step.is-done .shop-progress-dot {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1d4ed8;
        }
        .shop-progress-step.is-done { color: #1e40af; }
        .shop-progress-step.is-current .shop-progress-dot {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            transform: scale(1.08);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.28);
        }
        .shop-progress-step.is-current { color: #1d4ed8; }
        .shop-search {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
            padding: 0.35rem 0.4rem 0.35rem 0.85rem;
            background: var(--bg-elevated);
            border: 1px solid var(--line);
            border-radius: 999px;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.06);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .shop-search:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .shop-search-input {
            flex: 1;
            min-width: 0;
            border: 0;
            background: transparent;
            padding: 0.55rem 0;
            color: var(--ink);
            outline: none;
            font-size: 0.9375rem;
            -webkit-user-select: text;
            user-select: text;
        }
        .shop-search-input::placeholder { color: #94a3b8; }
        .shop-search-btn {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border: 0;
            border-radius: 999px;
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
            cursor: pointer;
            transition: transform 0.12s ease, background 0.12s ease;
        }
        .shop-search-btn:active { transform: scale(0.95); background: #bfdbfe; }
        .shop-search-btn svg { display: block; }
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
    @php
        $shopLogoUrl = $shopLogoUrl ?? \App\Support\PublicLanding::logoUrl();
        $shopBrandName = $brand ?? config('storefront.brand_name', 'New Online Optics');
    @endphp
    <div class="shop-shell {{ !empty($hasDock) ? 'has-dock' : '' }}">
        <header class="shop-top {{ !empty($stickyNav) ? 'is-sticky' : '' }}">
            <div class="shop-nav-left">
                @if(!empty($backUrl))
                    <a href="{{ $backUrl }}" class="shop-back" aria-label="{{ $backLabel ?? 'Back' }}">← {{ $backLabel ?? 'Back' }}</a>
                @endif
                <a href="{{ route('shop.gender') }}" class="shop-brand">
                    @if($shopLogoUrl)
                        <img src="{{ $shopLogoUrl }}" alt="{{ $shopBrandName }}" class="shop-brand-logo">
                    @endif
                    <span class="shop-brand-text">{{ $shopBrandName }}</span>
                </a>
            </div>
            @if(($cartCount ?? 0) > 0)
                <a href="{{ route('shop.cart') }}" class="cart-pill">Cart · {{ $cartCount }}</a>
            @endif
        </header>

        @include('shop.partials.progress')

        @if(!empty($showShopSearch))
            <form class="shop-search anim-in" action="{{ route('shop.search') }}" method="get" role="search">
                <input
                    class="shop-search-input"
                    type="search"
                    name="q"
                    value="{{ $searchQuery ?? '' }}"
                    placeholder="Search frames or brands…"
                    enterkeyhint="search"
                    autocomplete="off"
                    aria-label="Search frames or brands"
                >
                <button type="submit" class="shop-search-btn" aria-label="Search">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </form>
        @endif

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
            {{ $shopBrandName }} &copy; {{ config('storefront.copyright_year', date('Y')) }}
        </footer>
    </div>
    @stack('scripts')
    <script>
        // Block pinch-zoom only — do not intercept single taps (that made buttons feel dead).
        document.addEventListener('touchstart', function (e) {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
        document.addEventListener('gesturestart', function (e) { e.preventDefault(); }, { passive: false });
    </script>
</body>
</html>
