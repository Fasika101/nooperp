@extends('shop.layout')

@section('title', $gender->name.' frames — '.$brand)

@section('content')
    <div class="frames-head anim-in">
        <div>
            <p class="page-kicker">Step 2 · {{ $gender->name }}</p>
            <h1 class="page-title">Frames in stock</h1>
            <p class="page-sub" style="margin-bottom:0.75rem;">Choose color & size, then add to cart.</p>
        </div>
        <a href="{{ route('shop.gender') }}" class="btn btn-ghost btn-sm">Change</a>
    </div>

    @if($frames->isEmpty())
        <div class="card" style="padding:1.5rem;">
            <p style="margin:0;color:var(--ink-soft);">No frames with stock for {{ $gender->name }} right now.</p>
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
                        <p class="frame-stock">{{ $frame['stock'] }} in stock</p>
                        <div class="frame-actions">
                            <button type="button" class="btn btn-ghost btn-sm try-btn"
                                data-name="{{ $frame['name'] }}"
                                data-image="{{ $frame['image'] }}"
                                data-sku="{{ $frame['try_on_sku'] }}">Try</button>
                            <button type="button" class="btn btn-primary btn-sm pick-btn" style="flex:1;" data-product-id="{{ $frame['id'] }}">Add</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
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
                <button type="button" class="btn btn-ghost btn-sm" id="try-close">Close</button>
            </div>
            <div class="try-stage">
                <video id="try-video" playsinline muted autoplay></video>
                <img id="try-overlay" alt="" hidden>
                <p id="try-fallback" class="page-sub" hidden>Camera not available. Showing frame preview.</p>
            </div>
            <p id="try-banuba-hint" class="page-sub" style="margin:0.75rem 0 0;" hidden>
                Banuba TINT is not configured yet. Add <code>BANUBA_TINT_MERCHANT_ID</code> to <code>.env</code>.
            </p>
        </div>
    </div>

    @if($banubaReady ?? false)
        <tint-vto
            id="banuba-tint"
            merchant-id="{{ $banubaMerchantId }}"
            style="display:none"
        ></tint-vto>
        <script type="module" src="{{ $banubaWidgetUrl }}"></script>
    @endif

    <style>
        .frames-head { display:flex; align-items:flex-start; justify-content:space-between; gap:0.75rem; margin-bottom:1rem; }
        .frame-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
        .frame-card {
            background: var(--bg-elevated); border-radius: 1.1rem; overflow: hidden;
            border: 1px solid var(--line); display: flex; flex-direction: column;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .frame-card:active { transform: scale(0.98); }
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
        .frame-actions { display:flex; gap:0.35rem; margin-top:auto; padding-top:0.5rem; }
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
        }
        .chip:active { transform: scale(0.96); }
        .chip.is-on { background: #dbeafe; color: #1e40af; border-color: #93c5fd; animation: pop 0.25s ease; }
        .chip.is-off { opacity: 0.35; pointer-events: none; }
        .try-modal {
            position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,0.55);
            display:flex; align-items:flex-end; justify-content:center; padding: 1rem;
        }
        .try-panel {
            width: min(480px, 100%); background: var(--bg); border-radius: 1.25rem;
            padding: 1rem; max-height: 90dvh;
        }
        .try-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; }
        .try-stage {
            position: relative; aspect-ratio: 3/4; background: #111; border-radius: 1rem; overflow: hidden;
        }
        .try-stage video, .try-stage img {
            position:absolute; inset:0; width:100%; height:100%; object-fit:cover;
        }
        #try-overlay {
            object-fit: contain; width: 70%; height: auto; left: 15%; top: 28%;
            bottom: auto; right: auto; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.35));
        }
        #try-fallback { position:absolute; inset:auto 1rem 1rem; color:#fff; }
        #pick-form { margin-top: 1rem; }
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

            // Try-on (Banuba TINT when configured, else simple camera overlay)
            var banubaReady = @json($banubaReady ?? false);
            var modal = document.getElementById('try-modal');
            var video = document.getElementById('try-video');
            var overlay = document.getElementById('try-overlay');
            var fallback = document.getElementById('try-fallback');
            var stream = null;
            var tint = document.getElementById('banuba-tint');

            function stopTry() {
                if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
                video.srcObject = null;
                modal.hidden = true;
            }
            document.getElementById('try-close').addEventListener('click', stopTry);
            modal.addEventListener('click', function (e) { if (e.target === modal) stopTry(); });

            async function openBanubaTryOn(sku, name, imageUrl) {
                if (!tint || !sku) return false;
                try {
                    tint.setAttribute('sku', String(sku));
                    tint.setAttribute('isolated-sku', '');
                    if (typeof tint.open === 'function') {
                        await tint.open();
                    }
                    if (typeof tint.useWebcam === 'function') {
                        var webcamStream = await tint.useWebcam();
                        if (!webcamStream && imageUrl && typeof tint.useImage === 'function') {
                            try {
                                var blob = await fetch(imageUrl).then(function (r) { return r.blob(); });
                                tint.useImage(blob);
                            } catch (e) {}
                        }
                    }
                    return true;
                } catch (err) {
                    console.warn('Banuba TINT failed, using fallback', err);
                    return false;
                }
            }

            async function openFallbackTryOn(name, imageUrl) {
                document.getElementById('try-title').textContent = 'Try · ' + (name || '');
                overlay.src = imageUrl || '';
                overlay.hidden = !imageUrl;
                fallback.hidden = true;
                var hint = document.getElementById('try-banuba-hint');
                if (hint) hint.hidden = banubaReady;
                modal.hidden = false;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    video.srcObject = stream;
                } catch (err) {
                    fallback.hidden = false;
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
                    var sku = btn.dataset.sku || '';
                    if (banubaReady) {
                        var ok = await openBanubaTryOn(sku, name, image);
                        if (ok) return;
                    }
                    await openFallbackTryOn(name, image);
                });
            });
        })();
    </script>
@endsection
