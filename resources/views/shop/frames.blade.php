@extends('shop.layout')

@section('title', (!empty($isSearch) ? 'Search — '.$searchQuery : (($gender?->name ?? 'Frames').' frames')).' — '.$brand)

@section('content')
    <div class="frames-head anim-in">
        <div>
            <h1 class="page-title">
                @if(!empty($isSearch))
                    Results for “{{ $searchQuery }}”
                @else
                    {{ $gender->name }} frames
                @endif
            </h1>
            <p class="page-sub" style="margin-bottom:0;">
                @if(($totalFrames ?? $frames->count()) > 0)
                    Showing {{ $frames->count() }} of {{ $totalFrames ?? $frames->count() }} · page {{ $page ?? 1 }}
                @elseif(!empty($isSearch))
                    No in-stock frames matched that search.
                @else
                    Choose color &amp; size, then add to cart.
                @endif
            </p>
        </div>
    </div>

    @if($frames->isEmpty())
        <div class="card" style="padding:1.5rem;">
            <p style="margin:0;color:var(--ink-soft);">
                @if(!empty($isSearch))
                    Try another name or brand, or browse by category below.
                @else
                    No frames with stock for {{ $gender->name }} right now.
                @endif
            </p>
            @if(!empty($isSearch))
                <p style="margin:1rem 0 0;">
                    <a href="{{ route('shop.gender') }}" class="btn btn-primary" style="width:auto;display:inline-flex;">Browse by gender</a>
                </p>
            @endif
        </div>
    @else
        <div class="frame-grid">
            @foreach($frames as $i => $frame)
                <article class="frame-card anim-in" style="animation-delay: {{ min($i, 8) * 0.04 }}s"
                    data-product-id="{{ $frame['id'] }}">
                    <div class="frame-media" style="background-image:url('{{ $frame['image'] ?: 'https://ui-avatars.com/api/?name='.urlencode($frame['name']).'&background=DBEAFE&color=1D4ED8' }}');">
                        @if($frame['brand'])
                            <span class="frame-brand">{{ $frame['brand'] }}</span>
                        @endif
                    </div>
                    <div class="frame-body">
                        <h2 class="frame-name">{{ $frame['name'] }}</h2>
                        <p class="frame-price">{{ $currency }} {{ number_format($frame['price'], 2) }}</p>
                        <p class="frame-stock">
                            {{ $frame['stock'] }} in stock
                            @if(!empty($isSearch) && !empty($frame['gender_name']))
                                · {{ $frame['gender_name'] }}
                            @endif
                        </p>
                        <div class="frame-actions">
                            <button type="button" class="btn btn-ghost btn-sm try-btn"
                                data-product-id="{{ $frame['id'] }}"
                                data-name="{{ $frame['name'] }}"
                                data-image="{{ $frame['image'] }}"
                                data-effect="{{ $frame['try_on_effect_url'] ?? '' }}">Try</button>
                            @if(!empty($frame['location']['maps_url']))
                                <button type="button" class="btn btn-ghost btn-sm loc-btn"
                                    data-maps-url="{{ $frame['location']['maps_url'] }}"
                                    data-name="{{ $frame['location']['name'] }}"
                                    aria-label="Open shop location in Google Maps"
                                    title="{{ $frame['location']['name'] ?? 'Shop location' }}">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z"/>
                                        <circle cx="12" cy="11" r="2.2"/>
                                    </svg>
                                </button>
                            @endif
                            <button type="button" class="btn btn-primary btn-sm pick-btn" style="flex:1;" data-product-id="{{ $frame['id'] }}">Add</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if(($lastPage ?? 1) > 1)
            @php
                $pagerRoute = !empty($isSearch) ? 'shop.search' : 'shop.frames';
                $pagerBase = !empty($isSearch) ? ['q' => $searchQuery] : [];
            @endphp
            <div class="frame-pager anim-in">
                @if(($page ?? 1) > 1)
                    <a href="{{ route($pagerRoute, $pagerBase + ['page' => $page - 1]) }}" class="btn btn-ghost">← Previous</a>
                @endif
                <span class="frame-pager-meta">{{ $page ?? 1 }} / {{ $lastPage }}</span>
                @if(($page ?? 1) < ($lastPage ?? 1))
                    <a href="{{ route($pagerRoute, $pagerBase + ['page' => $page + 1]) }}" class="btn btn-accent">Next →</a>
                @endif
            </div>
        @endif
    @endif

    <div class="dock" style="pointer-events:none;">
        <div style="pointer-events:auto;">
            @if($cartCount > 0)
                <a href="{{ route('shop.cart') }}" class="btn btn-accent">Go to cart ({{ $cartCount }})</a>
            @endif
        </div>
    </div>

    <div id="pick-sheet" class="sheet" hidden>
        <div class="sheet-panel">
            <div class="sheet-top">
                <strong id="pick-title">Choose options</strong>
                <button type="button" class="btn btn-ghost btn-sm" id="pick-close">Close</button>
            </div>
            <p class="sheet-label">Color</p>
            <div id="pick-colors" class="chip-row"></div>
            <p class="sheet-label" id="pick-size-label">Size</p>
            <div id="pick-sizes" class="chip-row"></div>
            <form method="post" action="{{ route('shop.cart.add') }}" id="pick-form">
                @csrf
                <input type="hidden" name="product_id" id="pick-product-id" value="">
                <input type="hidden" name="color_option_id" id="pick-color-id" value="">
                <input type="hidden" name="size_option_id" id="pick-size-id" value="">
                <button type="submit" class="btn btn-accent" id="pick-submit">Add to cart</button>
            </form>
        </div>
    </div>

    <div id="try-modal" class="try-modal" hidden>
        <div class="try-panel">
            <div class="try-top">
                <strong id="try-title">Try on</strong>
                <div class="try-top-actions">
                    <button type="button" class="btn btn-ghost btn-sm" id="pd-toggle" hidden aria-pressed="true">Hide PD</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="try-close">Close</button>
                </div>
            </div>
            <div class="try-stage" id="try-stage">
                <div id="deepar-preview" class="deepar-preview" hidden></div>
                <video id="try-video" playsinline muted autoplay></video>
                <img id="try-overlay" alt="" hidden>
                {{-- PD meter overlay: face guide + live reading. Toggle with "Hide PD". --}}
                <div id="pd-layer" class="pd-layer" hidden aria-live="polite">
                    <div class="pd-guide" id="pd-guide">
                        <div class="pd-guide-oval"></div>
                        <div class="pd-guide-eyes"></div>
                        {{-- live iris trackers, positioned by the scanner each frame --}}
                        <span class="pd-eye" id="pd-eye-r" hidden></span>
                        <span class="pd-eye" id="pd-eye-l" hidden></span>
                        <div class="pd-arrow pd-arrow-left" id="pd-arrow-left" hidden aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>
                        </div>
                        <div class="pd-arrow pd-arrow-right" id="pd-arrow-right" hidden aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                    <div class="pd-pill" id="pd-pill">
                        <span class="pd-pill-label">PD</span>
                        <span class="pd-pill-value" id="pd-pill-value">—</span>
                    </div>
                    <div class="pd-steps" id="pd-steps"></div>
                    <button type="button" class="pd-skip" id="pd-skip" hidden>Skip turns</button>
                    <div class="pd-coach-wrap">
                        <div class="pd-progress-track"><div class="pd-progress" id="pd-progress"></div></div>
                        <div class="pd-coach" id="pd-coach">Face the camera straight, about 40 cm (16 in) away.</div>
                    </div>
                </div>
                <p id="try-fallback" class="page-sub" hidden>Camera not available. Showing frame preview.</p>
            </div>
            <div id="pd-verdict" class="pd-verdict" hidden></div>
            <div id="pd-results" class="pd-results" hidden></div>
            <div id="pd-actions" class="pd-actions" hidden>
                <button type="button" class="btn btn-ghost btn-sm" id="pd-remeasure">Scan again</button>
                <form class="pd-manual" id="pd-manual" novalidate>
                    <label for="pd-manual-input">Know your PD?</label>
                    <input type="number" id="pd-manual-input" inputmode="decimal" min="40" max="90" step="0.5" placeholder="64">
                    <button type="submit" class="btn btn-ghost btn-sm">Use</button>
                </form>
                <span class="pd-note">Camera estimate (±2–3 mm) for frame fit only — not for ordering lenses.</span>
            </div>
            <p id="try-hint" class="page-sub" style="margin:0.75rem 0 0;" hidden></p>
        </div>
    </div>

    @if($deeparReady ?? false)
        {{-- Load without defer so window.deepar is ready when Try is tapped --}}
        <script src="{{ $deeparSdkUrl }}"></script>
    @endif

    <style>
        .frames-head { display:flex; align-items:flex-start; justify-content:space-between; gap:0.75rem; margin-bottom:1rem; }
        .frame-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
        .frame-pager {
            display: flex; align-items: center; justify-content: center; gap: 0.65rem;
            flex-wrap: wrap; margin: 1.25rem 0 0.5rem;
        }
        .frame-pager .btn { width: auto; min-width: 7.5rem; padding: 0.75rem 1.1rem; }
        .frame-pager-meta {
            font-size: 0.8125rem; font-weight: 700; color: var(--ink-soft);
            min-width: 3rem; text-align: center;
        }
        .frame-card {
            background: var(--bg-elevated); border-radius: 1.1rem; overflow: hidden;
            border: 1px solid var(--line); display: flex; flex-direction: column;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .frame-card:active { transform: scale(0.98); }
        .frame-actions .btn, .frame-pager .btn {
            touch-action: manipulation;
            min-height: 2.5rem;
        }
        .frame-media {
            aspect-ratio: 1 / 1; background: #e8eefc center/cover no-repeat; position: relative;
        }
        .frame-brand {
            position: absolute; left: 0.5rem; top: 0.5rem; background: rgba(255,255,255,0.95);
            font-size: 0.65rem; font-weight: 700; color: var(--accent);
            padding: 0.2rem 0.45rem; border-radius: 999px;
        }
        .frame-body { padding: 0.75rem; display:flex; flex-direction:column; gap:0.25rem; flex:1; }
        .frame-name { font-size: 0.9rem; margin:0; font-weight:700; line-height:1.25; }
        .frame-price { margin:0; font-weight:700; color: var(--accent); font-size:0.9rem; }
        .frame-stock { margin:0; font-size:0.7rem; color: var(--ink-soft); }
        .frame-actions {
            display:flex; flex-wrap: wrap; gap:0.35rem; margin-top:auto; padding-top:0.5rem;
        }
        .frame-actions .btn { flex: 1 1 auto; min-width: 0; padding-left: 0.55rem; padding-right: 0.55rem; font-size: 0.75rem; }
        .frame-actions .pick-btn {
            flex: 1 1 100%;
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        .frame-actions .loc-btn {
            flex: 0 0 auto;
            min-width: 2.5rem;
            width: 2.5rem;
            padding-left: 0;
            padding-right: 0;
            color: #dc2626;
            border-color: #fecaca;
            background: #fef2f2;
        }
        .frame-actions .loc-btn:hover,
        .frame-actions .loc-btn:active {
            color: #b91c1c;
            background: #fee2e2;
            border-color: #fca5a5;
        }
        .frame-actions .loc-btn svg { display: block; }
        .frame-actions .pick-btn:hover { background: #bfdbfe; color: var(--accent-btn-text); }
        .sheet-panel #pick-submit {
            background: var(--accent-btn);
            color: var(--accent-btn-text);
            border: 1px solid var(--accent-border);
        }
        .sheet {
            position: fixed; inset: 0; z-index: 55; background: rgba(15,23,42,0.45);
            display:flex; align-items:flex-end; justify-content:center;
        }
        .sheet[hidden], .try-modal[hidden] { display:none !important; }
        .sheet-panel {
            width: min(480px, 100%); background: #fff; border-radius: 1.25rem 1.25rem 0 0;
            padding: 1rem 1rem calc(1rem + var(--safe-b));
            animation: fadeUp 0.28s ease;
        }
        .sheet-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; }
        .sheet-label {
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--ink-soft); margin: 0.75rem 0 0.4rem;
        }
        .chip-row { display:flex; flex-wrap:wrap; gap:0.4rem; min-height: 2rem; }
        .chip {
            border: 1px solid var(--line); background: #fff; border-radius: 999px;
            padding: 0.45rem 0.8rem; font-size: 0.8rem; font-weight: 600; cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
            color: var(--ink);
        }
        .chip:active { transform: scale(0.96); }
        .chip.is-on { background: var(--accent-btn); color: var(--accent-btn-text); border-color: #93c5fd; animation: pop 0.25s ease; }
        .chip.is-off { opacity: 0.35; pointer-events: none; }
        .try-modal {
            position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,0.55);
            display:flex; align-items:flex-end; justify-content:center; padding: 1rem;
        }
        .try-panel {
            width: min(480px, 100%); background: var(--bg); border-radius: 1.25rem;
            padding: 1rem; max-height: 90dvh; overflow-y: auto;
        }
        .try-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; }
        .try-stage {
            position: relative; aspect-ratio: 3/4; background: #111; border-radius: 1rem; overflow: hidden;
        }
        .try-stage video, .try-stage img, .deepar-preview {
            position:absolute; inset:0; width:100%; height:100%; object-fit:cover;
        }
        .deepar-preview { z-index: 2; background: #111; }
        .deepar-preview canvas { width: 100% !important; height: 100% !important; object-fit: cover; }
        #try-overlay {
            object-fit: contain; width: 70%; height: auto; left: 15%; top: 28%;
            bottom: auto; right: auto; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.35));
        }
        #try-fallback { position:absolute; inset:auto 1rem 1rem; color:#fff; z-index: 3; }
        #pick-form { margin-top: 1rem; }
        .try-top-actions { display:flex; gap:0.4rem; align-items:center; }
        .try-top-actions .btn { width:auto; }
        /* Selfie-style mirror for the plain camera preview (DeepAR mirrors itself) */
        #try-video.is-mirrored { transform: scaleX(-1); }

        /* ---- PD meter overlay ---- */
        .pd-layer { position:absolute; inset:0; z-index:4; pointer-events:none; }
        .pd-layer[hidden] { display:none !important; }
        .pd-guide { position:absolute; inset:0; transition: opacity 0.25s ease; }
        .pd-guide.is-off { opacity:0; }
        .pd-guide-oval {
            position:absolute; left:50%; top:46%; width:62%; aspect-ratio: 3 / 4;
            transform: translate(-50%, -50%);
            border: 2px dashed rgba(255,255,255,0.85); border-radius: 50% / 46%;
            box-shadow: 0 0 0 9999px rgba(15,23,42,0.28);
        }
        .pd-guide.is-good .pd-guide-oval { border-color: #4ade80; border-style: solid; }
        .pd-guide-eyes {
            position:absolute; left:50%; top:38%; width:62%; transform: translateX(-50%);
            display:flex; justify-content:center; gap: 18%;
        }
        .pd-guide-eyes::before {
            content:""; position:absolute; left:8%; right:8%; top:50%; height:1px;
            background: rgba(255,255,255,0.55);
        }
        .pd-guide-eyes { height: 1.6rem; }
        .pd-eye {
            position:absolute; left:0; top:0; width: 1.6rem; height: 1.6rem; border-radius:50%;
            border: 2px solid rgba(255,255,255,0.95); box-shadow: 0 0 0 2px rgba(15,23,42,0.35);
            transform: translate(-50%, -50%);
            transition: transform 0.08s linear, width 0.12s ease, height 0.12s ease, border-color 0.2s ease;
            will-change: transform;
        }
        .pd-eye::after {
            content:""; position:absolute; left:50%; top:50%; width:4px; height:4px; border-radius:50%;
            background: currentColor; transform: translate(-50%, -50%); color: rgba(255,255,255,0.95);
        }
        .pd-eye[hidden] { display:none !important; }
        .pd-guide.is-good .pd-eye { border-color:#4ade80; color:#4ade80; }
        .pd-pill {
            position:absolute; left:0.65rem; top:0.65rem; display:flex; align-items:baseline; gap:0.35rem;
            background: rgba(15,23,42,0.72); color:#fff; backdrop-filter: blur(6px);
            padding: 0.4rem 0.7rem; border-radius: 999px; font-weight:700; font-size:0.85rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .pd-pill-label { font-size:0.65rem; letter-spacing:0.08em; text-transform:uppercase; opacity:0.8; }
        .pd-pill.is-locked { background: rgba(22,101,52,0.85); border-color: rgba(134,239,172,0.6); }
        .pd-pill.is-locked .pd-pill-value::after { content:" ✓"; }
        .pd-coach-wrap { position:absolute; left:0.65rem; right:0.65rem; bottom:0.65rem; display:flex; flex-direction:column; gap:0.35rem; }
        .pd-coach {
            text-align:center;
            background: rgba(15,23,42,0.72); color:#fff; backdrop-filter: blur(6px);
            padding: 0.5rem 0.75rem; border-radius: 0.75rem; font-size:0.8rem; font-weight:600; line-height:1.3;
        }
        .pd-coach:empty { display:none; }
        .pd-progress-track { height: 4px; border-radius: 999px; background: rgba(255,255,255,0.25); overflow:hidden; }
        .pd-progress { height:100%; width:0; background:#4ade80; transition: width 0.15s linear; }
        .pd-steps {
            position:absolute; right:0.65rem; top:0.65rem; background: rgba(15,23,42,0.72); color:#fff;
            padding: 0.35rem 0.6rem; border-radius: 999px; font-size:0.7rem; font-weight:700; letter-spacing:0.02em;
            backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,0.2);
        }
        .pd-steps:empty { display:none; }
        .pd-arrow {
            position:absolute; top:44%; transform: translateY(-50%); color:#fff;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5)); animation: pdNudge 0.9s ease-in-out infinite alternate;
        }
        .pd-arrow[hidden] { display:none !important; }
        .pd-arrow-left { left: 4%; }
        .pd-arrow-right { right: 4%; --dir: 1; }
        .pd-arrow-left { --dir: -1; }
        @keyframes pdNudge { from { translate: 0 0; } to { translate: calc(var(--dir, 1) * 8px) 0; } }
        .pd-skip {
            position:absolute; right:0.65rem; top:2.9rem; pointer-events:auto;
            background: rgba(255,255,255,0.92); color: var(--ink); border: 0; border-radius: 999px;
            padding: 0.35rem 0.7rem; font: inherit; font-size:0.72rem; font-weight:700; cursor:pointer;
        }
        .pd-skip[hidden] { display:none !important; }
        .pd-results {
            margin-top: 0.6rem; display:grid; grid-template-columns: 1fr 1fr; gap: 0.4rem 0.6rem;
            background: var(--bg-elevated); border:1px solid var(--line); border-radius: 0.85rem; padding: 0.7rem 0.85rem;
        }
        .pd-results[hidden] { display:none !important; }
        .pd-res { display:flex; flex-direction:column; gap:0.1rem; }
        .pd-res span { font-size:0.65rem; text-transform:uppercase; letter-spacing:0.06em; color: var(--ink-soft); font-weight:700; }
        .pd-res strong { font-size:0.95rem; }
        .pd-res strong small { font-size:0.7rem; font-weight:600; color: var(--ink-soft); }
        .pd-res-note { grid-column: 1 / -1; font-size:0.7rem; color: var(--ink-soft); }
        .pd-verdict {
            margin: 0.75rem 0 0; padding: 0.7rem 0.85rem; border-radius: 0.85rem;
            font-size: 0.85rem; font-weight: 600; line-height: 1.35; border: 1px solid transparent;
        }
        .pd-verdict[hidden] { display:none !important; }
        .pd-verdict.is-good { background:#dcfce7; color:#14532d; border-color:#86efac; }
        .pd-verdict.is-big { background:#fef3c7; color:#78350f; border-color:#fcd34d; }
        .pd-verdict.is-small { background:#fee2e2; color:#7f1d1d; border-color:#fca5a5; }
        .pd-verdict.is-neutral { background:#e0e7ff; color:#312e81; border-color:#c7d2fe; }
        .pd-verdict small { display:block; font-weight:500; opacity:0.85; margin-top:0.2rem; }
        .pd-actions { display:flex; align-items:center; gap:0.6rem; margin-top:0.6rem; flex-wrap:wrap; }
        .pd-actions[hidden] { display:none !important; }
        .pd-actions .btn { width:auto; }
        .pd-note { font-size:0.7rem; color: var(--ink-soft); flex:1 1 12rem; }
        .pd-manual { display:flex; align-items:center; gap:0.35rem; margin:0; }
        .pd-manual label { font-size:0.75rem; font-weight:600; color: var(--ink-soft); white-space:nowrap; }
        .pd-manual input {
            width: 4.2rem; padding: 0.4rem 0.5rem; border:1px solid var(--line); border-radius: 0.6rem;
            font: inherit; font-size:0.85rem; font-weight:600; text-align:center; background:#fff; color: var(--ink);
        }
        .pd-manual .btn { padding-left:0.7rem; padding-right:0.7rem; }
        .pd-pill.is-manual .pd-pill-value::after { content:" ✎"; }
    </style>

    <script>
        window.SHOP_FRAMES = @json($frames->keyBy('id'));
    </script>
    <script>
        (function () {
            var frames = window.SHOP_FRAMES || {};
            var sheet = document.getElementById('pick-sheet');
            var colorsEl = document.getElementById('pick-colors');
            var sizesEl = document.getElementById('pick-sizes');
            var sizeLabel = document.getElementById('pick-size-label');
            var productInput = document.getElementById('pick-product-id');
            var colorInput = document.getElementById('pick-color-id');
            var sizeInput = document.getElementById('pick-size-id');
            var submitBtn = document.getElementById('pick-submit');
            var current = null;
            var selectedColor = null;
            var selectedSize = null;

            function closeSheet() { sheet.hidden = true; }
            document.getElementById('pick-close').addEventListener('click', closeSheet);
            sheet.addEventListener('click', function (e) { if (e.target === sheet) closeSheet(); });

            function syncForm() {
                productInput.value = current ? String(current.id) : '';
                colorInput.value = (selectedColor && selectedColor.id) ? String(selectedColor.id) : '';
                sizeInput.value = (selectedSize && selectedSize.id) ? String(selectedSize.id) : '';
                var needsSize = !!(selectedColor && (selectedColor.sizes || []).some(function (s) { return !!s.id; }));
                var ok = !!current && !!selectedColor && (!needsSize || !!(selectedSize && selectedSize.id));
                submitBtn.disabled = !ok;
            }

            function renderSizes() {
                sizesEl.innerHTML = '';
                var sizes = (selectedColor && selectedColor.sizes) ? selectedColor.sizes : [];
                var realSizes = sizes.filter(function (s) { return !!s.id; });
                if (!realSizes.length) {
                    sizeLabel.hidden = true;
                    sizesEl.hidden = true;
                    selectedSize = sizes[0] || { id: null };
                    syncForm();
                    return;
                }
                sizeLabel.hidden = false;
                sizesEl.hidden = false;
                realSizes.forEach(function (s, idx) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip' + (idx === 0 ? ' is-on' : '');
                    btn.textContent = s.name || ('Size ' + s.id);
                    if ((s.quantity || 0) < 1) btn.classList.add('is-off');
                    btn.addEventListener('click', function () {
                        selectedSize = s;
                        sizesEl.querySelectorAll('.chip').forEach(function (x) { x.classList.remove('is-on'); });
                        btn.classList.add('is-on');
                        syncForm();
                    });
                    sizesEl.appendChild(btn);
                });
                selectedSize = realSizes[0];
                syncForm();
            }

            function renderColors() {
                colorsEl.innerHTML = '';
                var list = current.colors || [];
                if (!list.length) {
                    selectedColor = { id: null, name: 'Standard', sizes: [] };
                    renderSizes();
                    syncForm();
                    return;
                }
                list.forEach(function (c, idx) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip' + (idx === 0 ? ' is-on' : '');
                    btn.textContent = c.name || ('Color ' + c.id);
                    btn.addEventListener('click', function () {
                        selectedColor = c;
                        selectedSize = null;
                        colorsEl.querySelectorAll('.chip').forEach(function (x) { x.classList.remove('is-on'); });
                        btn.classList.add('is-on');
                        renderSizes();
                    });
                    colorsEl.appendChild(btn);
                });
                selectedColor = list[0];
                renderSizes();
            }

            function openPicker(productId) {
                current = frames[String(productId)] || frames[productId] || null;
                if (!current) {
                    alert('Could not load this frame. Please refresh.');
                    return;
                }
                document.getElementById('pick-title').textContent = current.name || 'Choose options';
                selectedColor = null;
                selectedSize = null;
                renderColors();
                sheet.hidden = false;
            }

            document.querySelectorAll('.pick-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openPicker(btn.getAttribute('data-product-id'));
                });
            });

            document.getElementById('pick-form').addEventListener('submit', function (e) {
                syncForm();
                if (submitBtn.disabled) {
                    e.preventDefault();
                    alert('Please choose a color and size.');
                }
            });

            // Try-on: DeepAR when license + product .deepar are set; else simple camera overlay
            var deeparReady = @json($deeparReady ?? false);
            var deeparLicenseKey = @json($deeparLicenseKey ?? '');
            var deeparRootPath = 'https://cdn.jsdelivr.net/npm/deepar/';
            var modal = document.getElementById('try-modal');
            var video = document.getElementById('try-video');
            var overlay = document.getElementById('try-overlay');
            var fallback = document.getElementById('try-fallback');
            var preview = document.getElementById('deepar-preview');
            var hint = document.getElementById('try-hint');
            var stream = null;
            var deepARInstance = null;
            var currentFrame = null; // product JSON for the frame being tried (pd_fit_* lives here)

            // ---------------------------------------------------------------
            // PD meter — runs 100% in the browser (MediaPipe Face Landmarker).
            // Scale: iris diameter ≈ 11.7 mm. Nothing is uploaded to the server.
            // ---------------------------------------------------------------
            var pdCfg = @json($pdMeter ?? []);
            // 720p front camera: a sharper iris (~20+ px) is what makes PD accurate.
            // Browsers fall back gracefully when the camera can't do it.
            var CAMERA_CONSTRAINTS = {
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            };
            // Guided face scan + PD meter lives in public/js/shop/pd-face-scan.js (ES module).
            // It is imported lazily the first time "Try" is tapped; until then PD is a stub.
            var pdModuleUrl = @json($pdScanUrl ?? '');
            var pdEls = {
                layer: document.getElementById('pd-layer'),
                stage: document.getElementById('try-stage'),
                guide: document.getElementById('pd-guide'),
                eyeR: document.getElementById('pd-eye-r'),
                eyeL: document.getElementById('pd-eye-l'),
                pill: document.getElementById('pd-pill'),
                pillValue: document.getElementById('pd-pill-value'),
                coach: document.getElementById('pd-coach'),
                steps: document.getElementById('pd-steps'),
                arrowLeft: document.getElementById('pd-arrow-left'),
                arrowRight: document.getElementById('pd-arrow-right'),
                progress: document.getElementById('pd-progress'),
                skip: document.getElementById('pd-skip'),
                toggle: document.getElementById('pd-toggle'),
                verdict: document.getElementById('pd-verdict'),
                results: document.getElementById('pd-results'),
                actions: document.getElementById('pd-actions'),
                remeasure: document.getElementById('pd-remeasure'),
                manualForm: document.getElementById('pd-manual'),
                manualInput: document.getElementById('pd-manual-input')
            };
            var pdEnabled = !!(pdCfg && pdCfg.enabled && window.isSecureContext && pdModuleUrl);
            var PD = {
                enabled: pdEnabled,
                _impl: null,
                _load: function () {
                    if (this._impl) return Promise.resolve(this._impl);
                    var self = this;
                    return import(pdModuleUrl).then(function (mod) {
                        // Preview is mirrored (selfie view) in both the DeepAR and plain-camera paths
                        self._impl = mod.createPdScanner({ cfg: pdCfg, camera: CAMERA_CONSTRAINTS, els: pdEls, mirrored: true });
                        return self._impl;
                    });
                },
                start: function (videoEl, lockedCb, stateCb) {
                    if (!pdEnabled) return Promise.resolve(false);
                    return this._load().then(function (impl) { return impl.start(videoEl, lockedCb, stateCb); })
                        .catch(function (e) { console.warn('PD module failed to load', e); return false; });
                },
                stop: function () { if (this._impl) this._impl.stop(); },
                renderVerdict: function (frame) { if (this._impl) this._impl.renderVerdict(frame); },
                hasStored: function () { return this._impl ? this._impl.hasStored() : !!sessionStorage.getItem('shop_pd_scan'); },
                willMeasure: function () {
                    if (!pdEnabled) return false;
                    try { return !sessionStorage.getItem('shop_pd_scan'); } catch (e) { return true; }
                }
            };
            if (pdEnabled) { PD._load().catch(function () {}); } // warm the module (tiny) early
            window.__shopPd = PD; // support/debug handle (read-only use)

            // ---- Glasses visibility: hidden while PD is measuring so the eyes stay clear ----
            var currentEffectUrl = '';
            var glassesShown = true;
            var glassesQueue = Promise.resolve();

            function setGlasses(show) {
                if (glassesShown === show) return;
                glassesShown = show;
                glassesQueue = glassesQueue.then(async function () {
                    if (deepARInstance) {
                        try {
                            if (show) {
                                if (currentEffectUrl) await deepARInstance.switchEffect(currentEffectUrl);
                            } else if (typeof deepARInstance.clearEffect === 'function') {
                                deepARInstance.clearEffect();
                            }
                        } catch (e) { console.warn('DeepAR effect toggle failed', e); }
                    } else {
                        overlay.hidden = show ? !overlay.getAttribute('src') : true;
                    }
                }).catch(function () {});
            }

            function startPdFor(videoEl) {
                if (!PD.enabled) { setGlasses(true); return; }
                PD.start(
                    videoEl,
                    function () { PD.renderVerdict(currentFrame); },
                    function (state) { setGlasses(state !== 'measuring'); }
                ).then(function (ok) {
                    if (!ok) setGlasses(true); // PD meter unavailable → just show the glasses
                }).catch(function () { setGlasses(true); });
            }

            function setHint(msg) {
                if (!hint) return;
                if (msg) {
                    hint.hidden = false;
                    hint.textContent = msg;
                } else {
                    hint.hidden = true;
                    hint.textContent = '';
                }
            }

            function cameraSecureHint() {
                if (window.isSecureContext) return '';
                return ' Camera needs HTTPS (or localhost). Open this shop via https://' + location.hostname + ' in Herd.';
            }

            function errText(err) {
                if (!err) return 'Unknown error';
                if (typeof err === 'string') return err;
                return err.message || err.toString() || 'Unknown error';
            }

            async function waitForDeepAR(ms) {
                var deadline = Date.now() + (ms || 8000);
                while (Date.now() < deadline) {
                    if (typeof window.deepar !== 'undefined' && window.deepar && typeof window.deepar.initialize === 'function') {
                        return window.deepar;
                    }
                    await new Promise(function (r) { setTimeout(r, 100); });
                }
                return null;
            }

            async function stopTry() {
                PD.stop();
                currentFrame = null;
                currentEffectUrl = '';
                glassesShown = true;
                glassesQueue = Promise.resolve();
                if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
                video.srcObject = null;
                video.hidden = false;
                video.classList.remove('is-mirrored');
                overlay.hidden = true;
                if (preview) {
                    preview.hidden = true;
                    preview.innerHTML = '';
                }
                setHint('');
                if (deepARInstance && typeof deepARInstance.shutdown === 'function') {
                    try { await deepARInstance.shutdown(); } catch (e) {}
                    deepARInstance = null;
                }
                modal.hidden = true;
            }
            document.getElementById('try-close').addEventListener('click', function () { stopTry(); });
            modal.addEventListener('click', function (e) { if (e.target === modal) stopTry(); });

            async function openDeepARTryOn(effectUrl, name) {
                if (!deeparReady || !deeparLicenseKey || !effectUrl) {
                    return { ok: false, reason: !effectUrl ? 'No effect file on this product.' : 'DeepAR license missing.' };
                }

                document.getElementById('try-title').textContent = 'Try · ' + (name || '');
                video.hidden = true;
                overlay.hidden = true;
                fallback.hidden = true;
                preview.hidden = false;
                preview.innerHTML = '';
                setHint('Starting virtual try-on…');
                modal.hidden = false;

                var api = await waitForDeepAR(8000);
                if (!api) {
                    return { ok: false, reason: 'DeepAR SDK did not load. Check network / CDN.' };
                }

                try {
                    // Absolute effect URL + rootPath for WASM/models
                    var absEffect = effectUrl;
                    if (absEffect.indexOf('http') !== 0) {
                        absEffect = new URL(absEffect, window.location.origin).href;
                    }

                    // One camera for both: we own the stream, DeepAR renders from our <video>,
                    // and the PD meter reads the same frames. Falls back to DeepAR's camera.
                    var sharedCam = false;
                    if (PD.enabled) {
                        try {
                            stream = await navigator.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS);
                            video.srcObject = stream;
                            video.hidden = false; // kept under the DeepAR canvas (z-index) so it keeps decoding
                            await video.play().catch(function () {});
                            sharedCam = true;
                        } catch (camErr) {
                            console.warn('Shared camera unavailable, DeepAR will open its own', camErr);
                            stream = null;
                        }
                    }

                    // Start with NO glasses when the PD meter is about to measure; the effect is
                    // switched in once the reading locks (or the user hides the PD overlay).
                    currentEffectUrl = absEffect;
                    glassesShown = !PD.willMeasure();

                    var buildOptions = function (useOwnVideo) {
                        return {
                            licenseKey: deeparLicenseKey,
                            previewElement: preview,
                            effect: glassesShown ? absEffect : undefined,
                            rootPath: deeparRootPath,
                            additionalOptions: {
                                cameraConfig: useOwnVideo
                                    ? { disableDefaultCamera: true }
                                    : { facingMode: 'user' }
                            }
                        };
                    };

                    deepARInstance = await api.initialize(buildOptions(sharedCam));

                    if (sharedCam) {
                        if (typeof deepARInstance.setVideoElement === 'function') {
                            deepARInstance.setVideoElement(video, true);
                        } else {
                            // Older SDK: let DeepAR run its own camera; PD keeps our stream.
                            try { await deepARInstance.shutdown(); } catch (e) {}
                            deepARInstance = await api.initialize(buildOptions(false));
                        }
                    }

                    setHint('');
                    // PD meter: shared <video> when we own the camera, else its own stream
                    startPdFor(sharedCam ? video : null);
                    return { ok: true };
                } catch (err) {
                    console.warn('DeepAR failed', err);
                    var reason = errText(err) + cameraSecureHint();
                    // Common license mistake: domain not registered as liba_noop.test
                    if (/license|domain|app key|unauthorized|forbidden/i.test(reason)) {
                        reason += ' In DeepAR portal, add domain “' + location.hostname + '” (no https://).';
                    }
                    preview.hidden = true;
                    preview.innerHTML = '';
                    video.hidden = false;
                    // Release the shared camera so the fallback can open its own cleanly
                    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
                    video.srcObject = null;
                    if (deepARInstance && typeof deepARInstance.shutdown === 'function') {
                        try { await deepARInstance.shutdown(); } catch (e) {}
                        deepARInstance = null;
                    }
                    return { ok: false, reason: reason };
                }
            }

            async function openFallbackTryOn(name, imageUrl, message) {
                document.getElementById('try-title').textContent = 'Try · ' + (name || '');
                preview.hidden = true;
                preview.innerHTML = '';
                video.hidden = false;
                overlay.src = imageUrl || '';
                glassesShown = !PD.willMeasure();
                overlay.hidden = !imageUrl || !glassesShown;
                fallback.hidden = true;
                setHint(message || (deeparReady ? '' : 'DeepAR is not configured. Add DEEPAR_LICENSE_KEY to .env.') || '');
                modal.hidden = false;
                try {
                    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                        throw new Error('insecure');
                    }
                    stream = await navigator.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS);
                    video.srcObject = stream;
                    video.classList.add('is-mirrored');
                    await video.play().catch(function () {});
                    startPdFor(video);
                } catch (err) {
                    fallback.hidden = false;
                    if (!message) {
                        setHint('Camera blocked.' + cameraSecureHint());
                    }
                    if (imageUrl) {
                        glassesShown = true; // no camera → no PD, just show the frame picture
                        overlay.style.inset = '0'; overlay.style.width = '100%';
                        overlay.style.objectFit = 'contain'; overlay.hidden = false;
                    }
                }
            }

            document.querySelectorAll('.try-btn').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    var name = btn.dataset.name || '';
                    var image = btn.dataset.image || '';
                    var effect = (btn.dataset.effect || '').trim();
                    var pid = btn.dataset.productId || '';
                    currentFrame = frames[String(pid)] || frames[pid] || null;
                    if (deeparReady && effect) {
                        var result = await openDeepARTryOn(effect, name);
                        if (result.ok) return;
                        await openFallbackTryOn(name, image, 'Virtual try-on failed: ' + (result.reason || 'unknown') + ' Showing a simple preview instead.');
                        return;
                    }
                    var msg = deeparReady && !effect
                        ? 'No DeepAR effect uploaded for this frame yet. Upload a .deepar file on the product in ERP.'
                        : null;
                    await openFallbackTryOn(name, image, msg);
                });
            });

            document.querySelectorAll('.loc-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var url = (btn.getAttribute('data-maps-url') || '').trim();
                    if (!url) {
                        alert('Shop location is not set for this frame yet.');
                        return;
                    }
                    window.open(url, '_blank', 'noopener,noreferrer');
                });
            });
        })();
    </script>
@endsection
