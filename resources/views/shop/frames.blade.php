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
                        <div class="pd-guide-eyes"><span></span><span></span></div>
                    </div>
                    <div class="pd-pill" id="pd-pill">
                        <span class="pd-pill-label">PD</span>
                        <span class="pd-pill-value" id="pd-pill-value">—</span>
                    </div>
                    <div class="pd-coach" id="pd-coach">Face the camera straight, about 40 cm (16 in) away.</div>
                </div>
                <p id="try-fallback" class="page-sub" hidden>Camera not available. Showing frame preview.</p>
            </div>
            <div id="pd-verdict" class="pd-verdict" hidden></div>
            <div id="pd-actions" class="pd-actions" hidden>
                <button type="button" class="btn btn-ghost btn-sm" id="pd-remeasure">Re-measure PD</button>
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
        .pd-guide-eyes span {
            width: 1.6rem; height: 1.6rem; border-radius:50%;
            border: 2px solid rgba(255,255,255,0.9); position:relative;
        }
        .pd-pill {
            position:absolute; left:0.65rem; top:0.65rem; display:flex; align-items:baseline; gap:0.35rem;
            background: rgba(15,23,42,0.72); color:#fff; backdrop-filter: blur(6px);
            padding: 0.4rem 0.7rem; border-radius: 999px; font-weight:700; font-size:0.85rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .pd-pill-label { font-size:0.65rem; letter-spacing:0.08em; text-transform:uppercase; opacity:0.8; }
        .pd-pill.is-locked { background: rgba(22,101,52,0.85); border-color: rgba(134,239,172,0.6); }
        .pd-pill.is-locked .pd-pill-value::after { content:" ✓"; }
        .pd-coach {
            position:absolute; left:0.65rem; right:0.65rem; bottom:0.65rem; text-align:center;
            background: rgba(15,23,42,0.72); color:#fff; backdrop-filter: blur(6px);
            padding: 0.5rem 0.75rem; border-radius: 0.75rem; font-size:0.8rem; font-weight:600; line-height:1.3;
        }
        .pd-coach:empty { display:none; }
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
            var PD = (function () {
                var enabled = !!(pdCfg && pdCfg.enabled && window.isSecureContext);
                var IRIS_MM = parseFloat(pdCfg.iris_diameter_mm) || 11.7;
                var NEED = 30;          // good frames before we lock a reading
                var INTERVAL = 120;     // ms between detections (~8 fps keeps phones cool)
                var ASSUMED_HFOV = 65;  // deg; only used to coach "move closer / back"
                var STORE_KEY = 'shop_pd_mm';

                var landmarker = null, loadPromise = null;
                var running = false, rafId = null, lastTs = 0, tickCount = 0;
                var samples = [];
                var result = null;          // { pd: Number, at: ms }
                var visible = true;
                var source = null;          // HTMLVideoElement we read frames from
                var ownStream = null;       // when PD had to open its own camera
                var ownVideo = null;
                var onLocked = null;

                var layer = document.getElementById('pd-layer');
                var guide = document.getElementById('pd-guide');
                var pill = document.getElementById('pd-pill');
                var pillValue = document.getElementById('pd-pill-value');
                var coachEl = document.getElementById('pd-coach');
                var toggleBtn = document.getElementById('pd-toggle');
                var verdictEl = document.getElementById('pd-verdict');
                var actionsEl = document.getElementById('pd-actions');
                var remeasureBtn = document.getElementById('pd-remeasure');

                var lum = document.createElement('canvas');
                lum.width = 32; lum.height = 24;
                var lumCtx = lum.getContext('2d', { willReadFrequently: true });

                function fmt(v) { return (Math.round(v * 2) / 2).toFixed(1).replace(/\.0$/, ''); }
                function coach(msg) { if (coachEl) coachEl.textContent = msg || ''; }
                function dist(a, b, w, h) {
                    var dx = (a.x - b.x) * w, dy = (a.y - b.y) * h;
                    return Math.sqrt(dx * dx + dy * dy);
                }
                function median(arr) {
                    var s = arr.slice().sort(function (a, b) { return a - b; });
                    var m = s.length >> 1;
                    return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
                }
                function quantile(sorted, q) {
                    var pos = (sorted.length - 1) * q, lo = Math.floor(pos), hi = Math.ceil(pos);
                    return sorted[lo] + (sorted[hi] - sorted[lo]) * (pos - lo);
                }

                function restore() {
                    try {
                        var raw = sessionStorage.getItem(STORE_KEY);
                        if (!raw) return null;
                        var v = JSON.parse(raw);
                        if (v && isFinite(v.pd) && v.pd > 40 && v.pd < 90) return v;
                    } catch (e) {}
                    return null;
                }
                function persist(v) {
                    try { sessionStorage.setItem(STORE_KEY, JSON.stringify(v)); } catch (e) {}
                }

                async function load() {
                    if (landmarker) return landmarker;
                    if (loadPromise) return loadPromise;
                    loadPromise = (async function () {
                        var vision = await import(pdCfg.tasks_vision_url);
                        var files = await vision.FilesetResolver.forVisionTasks(pdCfg.wasm_url);
                        var make = function (delegate) {
                            return vision.FaceLandmarker.createFromOptions(files, {
                                baseOptions: { modelAssetPath: pdCfg.model_url, delegate: delegate },
                                runningMode: 'VIDEO',
                                numFaces: 1,
                                outputFaceBlendshapes: false,
                                outputFacialTransformationMatrixes: false
                            });
                        };
                        try {
                            landmarker = await make('GPU');
                        } catch (e) {
                            console.warn('PD: GPU delegate failed, using CPU', e);
                            landmarker = await make('CPU');
                        }
                        return landmarker;
                    })();
                    try { return await loadPromise; } finally { loadPromise = null; }
                }

                function brightness(v) {
                    try {
                        lumCtx.drawImage(v, 0, 0, lum.width, lum.height);
                        var d = lumCtx.getImageData(0, 0, lum.width, lum.height).data;
                        var sum = 0;
                        for (var i = 0; i < d.length; i += 4) sum += 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
                        return sum / (d.length / 4);
                    } catch (e) { return 128; }
                }

                var lastLum = 128;

                function analyse(lm, w, h) {
                    // Iris centres + horizontal iris edges (MediaPipe refined landmarks)
                    var rC = lm[468], lC = lm[473];
                    var rW = dist(lm[469], lm[471], w, h);
                    var lW = dist(lm[474], lm[476], w, h);
                    var irisPx = (rW + lW) / 2;
                    var pdPx = dist(rC, lC, w, h);

                    // Head yaw from nose vs. outer eye corners (asymmetry ≈ turn)
                    var nose = lm[1], rCorner = lm[33], lCorner = lm[263];
                    var dR = Math.abs(nose.x - rCorner.x), dL = Math.abs(lCorner.x - nose.x);
                    var yawAsym = (dL - dR) / Math.max(1e-6, dL + dR);
                    // Roll from the iris line
                    var roll = Math.atan2((lC.y - rC.y) * h, (lC.x - rC.x) * w) * 180 / Math.PI;
                    // Rough distance from assumed FOV (coaching only)
                    var focalPx = (w / 2) / Math.tan(ASSUMED_HFOV / 2 * Math.PI / 180);
                    var distCm = irisPx > 0 ? (IRIS_MM * focalPx / irisPx) / 10 : 999;
                    // Face centring inside the oval
                    var cx = (rC.x + lC.x) / 2, cy = (rC.y + lC.y) / 2;

                    return {
                        pdMm: pdPx * (IRIS_MM / irisPx),
                        irisPx: irisPx,
                        yawAsym: yawAsym,
                        roll: roll,
                        distCm: distCm,
                        cx: cx, cy: cy,
                        irisSpread: Math.abs(rW - lW) / irisPx
                    };
                }

                function problem(a) {
                    if (a.irisPx < 7 || a.distCm > 62) return 'Move a little closer — about 40 cm (16 in) from the camera.';
                    if (a.distCm < 26) return 'Move back a little — about 40 cm (16 in) from the camera.';
                    if (Math.abs(a.yawAsym) > 0.22) return 'Turn to face the camera straight.';
                    if (Math.abs(a.roll) > 9) return 'Level your head — keep both eyes on the line.';
                    if (a.cx < 0.3 || a.cx > 0.7 || a.cy < 0.22 || a.cy > 0.62) return 'Center your face in the oval, eyes on the line.';
                    if (a.irisSpread > 0.22) return 'Look straight into the camera and hold still.';
                    if (lastLum < 55) return 'Need more light — face a window or lamp.';
                    return null;
                }

                function tick(ts) {
                    if (!running) return;
                    rafId = requestAnimationFrame(tick);
                    if (ts - lastTs < INTERVAL) return;
                    lastTs = ts;
                    var v = source;
                    if (!v || v.readyState < 2 || !v.videoWidth) return;
                    tickCount++;
                    if (tickCount % 6 === 0) lastLum = brightness(v);

                    var res;
                    try { res = landmarker.detectForVideo(v, performance.now()); } catch (e) { return; }
                    var lm = res && res.faceLandmarks && res.faceLandmarks[0];
                    if (!lm || lm.length < 478) {
                        samples = [];
                        guide.classList.remove('is-good');
                        coach('No face found — look at the camera, about 40 cm away.');
                        pillValue.textContent = '—';
                        return;
                    }
                    var a = analyse(lm, v.videoWidth, v.videoHeight);
                    var bad = problem(a);
                    if (bad) {
                        if (samples.length > 6) samples.splice(0, 3); // decay, don't nuke
                        guide.classList.remove('is-good');
                        coach(bad);
                        return;
                    }
                    guide.classList.add('is-good');
                    if (a.pdMm > 45 && a.pdMm < 85) samples.push(a.pdMm);
                    var live = samples.length ? median(samples) : a.pdMm;
                    pillValue.textContent = fmt(live) + ' mm';
                    coach('Hold still… ' + Math.min(100, Math.round(samples.length / NEED * 100)) + '%');

                    if (samples.length >= NEED) {
                        var s = samples.slice().sort(function (x, y) { return x - y; });
                        var spread = quantile(s, 0.75) - quantile(s, 0.25);
                        if (spread <= 2.0) {
                            lock(median(s));
                        } else {
                            samples = samples.slice(Math.floor(samples.length / 2));
                            coach('Almost — hold still a moment longer.');
                        }
                    }
                }

                function lock(pd) {
                    result = { pd: Math.round(pd * 2) / 2, at: Date.now() };
                    persist(result);
                    stopLoop();
                    paintLocked();
                    if (onLocked) onLocked(result);
                }

                function paintLocked() {
                    pill.classList.add('is-locked');
                    pillValue.textContent = fmt(result.pd) + ' mm';
                    guide.classList.add('is-off');
                    coach('');
                    actionsEl.hidden = !visible;
                }

                function paintMeasuring() {
                    pill.classList.remove('is-locked');
                    pillValue.textContent = '…';
                    guide.classList.remove('is-off', 'is-good');
                    coach('Face the camera straight, about 40 cm (16 in) away. Eyes on the line.');
                    actionsEl.hidden = true;
                    verdictEl.hidden = true;
                }

                function startLoop() {
                    if (running || !landmarker || !source) return;
                    running = true;
                    lastTs = 0;
                    rafId = requestAnimationFrame(tick);
                }
                function stopLoop() {
                    running = false;
                    if (rafId) cancelAnimationFrame(rafId);
                    rafId = null;
                }

                async function openOwnCamera() {
                    ownVideo = document.createElement('video');
                    ownVideo.setAttribute('playsinline', '');
                    ownVideo.muted = true;
                    ownStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    ownVideo.srcObject = ownStream;
                    await ownVideo.play().catch(function () {});
                    return ownVideo;
                }
                function closeOwnCamera() {
                    if (ownStream) { ownStream.getTracks().forEach(function (t) { t.stop(); }); ownStream = null; }
                    if (ownVideo) { ownVideo.srcObject = null; ownVideo = null; }
                }

                function applyVisibility() {
                    layer.hidden = !visible;
                    toggleBtn.textContent = visible ? 'Hide PD' : 'Show PD';
                    toggleBtn.setAttribute('aria-pressed', visible ? 'true' : 'false');
                    if (!visible) {
                        verdictEl.hidden = true;
                        actionsEl.hidden = true;
                        stopLoop();
                    } else {
                        if (result) { paintLocked(); if (onLocked) onLocked(result); }
                        else { paintMeasuring(); startLoop(); }
                    }
                }

                toggleBtn.addEventListener('click', function () {
                    visible = !visible;
                    applyVisibility();
                });
                remeasureBtn.addEventListener('click', function () {
                    result = null;
                    samples = [];
                    try { sessionStorage.removeItem(STORE_KEY); } catch (e) {}
                    visible = true;
                    applyVisibility();
                });

                /**
                 * Start the PD meter.
                 * @param {HTMLVideoElement|null} videoEl  shared camera element, or null to open our own
                 * @param {Function} lockedCb               called with {pd} when a reading is ready (or restored)
                 */
                async function start(videoEl, lockedCb) {
                    if (!enabled) return false;
                    onLocked = lockedCb || null;
                    toggleBtn.hidden = false;
                    visible = true;
                    samples = [];
                    result = restore();
                    layer.hidden = false;
                    pillValue.textContent = '…';
                    coach('Loading PD meter…');
                    try {
                        await load();
                        source = videoEl || await openOwnCamera();
                    } catch (e) {
                        console.warn('PD meter unavailable', e);
                        layer.hidden = true;
                        toggleBtn.hidden = true;
                        return false;
                    }
                    applyVisibility();
                    return true;
                }

                function stop() {
                    stopLoop();
                    closeOwnCamera();
                    source = null;
                    onLocked = null;
                    samples = [];
                    layer.hidden = true;
                    toggleBtn.hidden = true;
                    verdictEl.hidden = true;
                    actionsEl.hidden = true;
                    pill.classList.remove('is-locked');
                    guide.classList.remove('is-off', 'is-good');
                }

                /** Compare a locked PD with the product's ERP range and render the verdict. */
                function renderVerdict(frame) {
                    if (!result || !visible) { verdictEl.hidden = true; return; }
                    var pd = result.pd;
                    var min = frame && frame.pd_fit_min_mm != null ? parseFloat(frame.pd_fit_min_mm) : null;
                    var max = frame && frame.pd_fit_max_mm != null ? parseFloat(frame.pd_fit_max_mm) : null;
                    var TOL = 0.5; // half-step tolerance on the band edges
                    verdictEl.hidden = false;
                    verdictEl.className = 'pd-verdict';
                    if (min === null && max === null) {
                        verdictEl.classList.add('is-neutral');
                        verdictEl.innerHTML = 'Your PD is about <strong>' + fmt(pd) + ' mm</strong>.'
                            + '<small>Fit range is not set for this frame yet, so we can\'t confirm the fit.</small>';
                        return;
                    }
                    var range = (min !== null ? fmt(min) : '…') + '–' + (max !== null ? fmt(max) : '…') + ' mm';
                    if (min !== null && pd < min - TOL) {
                        verdictEl.classList.add('is-big');
                        verdictEl.innerHTML = 'This frame looks <strong>too big</strong> for you.'
                            + '<small>Your PD ≈ ' + fmt(pd) + ' mm · this frame fits ' + range + '. Try a narrower frame.</small>';
                    } else if (max !== null && pd > max + TOL) {
                        verdictEl.classList.add('is-small');
                        verdictEl.innerHTML = 'This frame looks <strong>too small</strong> for you.'
                            + '<small>Your PD ≈ ' + fmt(pd) + ' mm · this frame fits ' + range + '. Try a wider frame.</small>';
                    } else {
                        verdictEl.classList.add('is-good');
                        verdictEl.innerHTML = '<strong>Fits your PD.</strong> Measured ≈ ' + fmt(pd) + ' mm, within this frame\'s ' + range + ' range.';
                    }
                }

                return { enabled: enabled, start: start, stop: stop, renderVerdict: renderVerdict };
            })();

            function startPdFor(videoEl) {
                if (!PD.enabled) return;
                PD.start(videoEl, function () { PD.renderVerdict(currentFrame); }).catch(function () {});
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
                            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                            video.srcObject = stream;
                            video.hidden = false; // kept under the DeepAR canvas (z-index) so it keeps decoding
                            await video.play().catch(function () {});
                            sharedCam = true;
                        } catch (camErr) {
                            console.warn('Shared camera unavailable, DeepAR will open its own', camErr);
                            stream = null;
                        }
                    }

                    var buildOptions = function (useOwnVideo) {
                        return {
                            licenseKey: deeparLicenseKey,
                            previewElement: preview,
                            effect: absEffect,
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
                overlay.hidden = !imageUrl;
                fallback.hidden = true;
                setHint(message || (deeparReady ? '' : 'DeepAR is not configured. Add DEEPAR_LICENSE_KEY to .env.') || '');
                modal.hidden = false;
                try {
                    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                        throw new Error('insecure');
                    }
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
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
