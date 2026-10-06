@extends('shop.layout')

@section('title', 'Cart — '.$brand)

@section('content')
    <h1 class="page-title anim-in">Your cart</h1>
    <p class="page-sub anim-in">Scan prescription, pick a lens coating, then continue to delivery details.</p>

    <div class="card cart-items anim-in">
        @foreach($cart['items'] as $i => $item)
            <div class="cart-row">
                <div class="cart-thumb" style="background-image:url('{{ $item['image'] ?: 'https://ui-avatars.com/api/?name=Frame&background=DBEAFE&color=1D4ED8' }}');"></div>
                <div class="cart-meta">
                    <strong>{{ $item['name'] }}</strong>
                    <span>{{ $currency }} {{ number_format($item['price'], 2) }}</span>
                </div>
                <form method="post" action="{{ route('shop.cart.remove', $i) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-danger" aria-label="Remove">✕</button>
                </form>
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('shop.cart.continue') }}" id="continue-form" class="anim-in">
        @csrf

        <section class="block">
            <h2 class="block-title">Prescription</h2>
            <p class="block-sub">
                @if($geminiReady)
                    Upload a clear photo we scan it and estimate lens price for your prescription.
                @else
                    Upload a clear photo. Adds {{ $currency }} {{ number_format($prescriptionPrice, 2) }}.
                @endif
            </p>

            @if(!empty($cart['prescription']['scanned']))
                @php
                    $scan = $cart['prescription']['scan'] ?? [];
                    $priceMode = $cart['prescription']['price_mode'] ?? ($scan['pricing']['mode'] ?? 'exact');
                    $priceMin = $cart['prescription']['price_min'] ?? ($scan['pricing']['price_min'] ?? null);
                    $priceMax = $cart['prescription']['price_max'] ?? ($scan['pricing']['price_max'] ?? null);
                    $priceLabel = $cart['prescription']['price_label'] ?? ($scan['pricing']['label'] ?? 'Lens price');
                    $priceNote = $cart['prescription']['price_note'] ?? ($scan['pricing']['note'] ?? '');
                    // Hide internal pricing jargon from older scans
                    $priceNote = is_string($priceNote)
                        ? trim(str_ireplace([' using ERP lens tiers', ' from ERP lens tiers', 'ERP lens tiers'], '', $priceNote))
                        : '';
                    $visionType = ($scan['vision_type'] ?? $cart['prescription']['vision_type'] ?? 'single') === 'progressive'
                        ? 'progressive'
                        : 'single';
                    $visionLabel = $visionType === 'progressive' ? 'Progressive prescription' : 'Single prescription';
                    $chargePrice = (float) ($cart['prescription']['price'] ?? $prescriptionPrice);
                    if ($priceMode === 'range' && $priceMin !== null && $priceMax !== null && (float) $priceMax > (float) $priceMin) {
                        $priceDisplay = $currency.' '.number_format((float) $priceMin, 2).' – '.number_format((float) $priceMax, 2);
                    } elseif ($priceMode === 'quote') {
                        $priceDisplay = $priceMin ? ('From '.$currency.' '.number_format((float) $priceMin, 2)) : 'Lab quote';
                    } else {
                        $priceDisplay = $currency.' '.number_format($chargePrice, 2);
                    }
                @endphp
                <div class="rx-ok">
                    <div class="rx-ok-main">
                        <span>{{ $visionLabel }}</span>
                        <small>
                            {{ $priceLabel }}: {{ $priceDisplay }}
                            @if(!empty($scan['confidence']))
                                · {{ $scan['confidence'] }} confidence
                            @endif
                        </small>
                    </div>
                    <a href="#" onclick="event.preventDefault(); document.getElementById('clear-rx').submit();">Remove</a>
                </div>
                @if($priceNote !== '')
                    <p class="rx-price-note">{{ $priceNote }}</p>
                @endif
                @if($priceMode === 'range' && $chargePrice > 0)
                    <p class="rx-price-note">Charged estimate in total: {{ $currency }} {{ number_format($chargePrice, 2) }} (within the range above).</p>
                @endif
                @if(!empty($scan['right_eye']) || !empty($scan['left_eye']))
                    <div class="rx-values card">
                        <div class="rx-type-row">
                            <strong>Type</strong>
                            <span class="rx-type-badge {{ $visionType === 'progressive' ? 'is-progressive' : 'is-single' }}">{{ $visionLabel }}</span>
                        </div>
                        <div class="rx-eye">
                            <strong>OD (Right)</strong>
                            <span>SPH {{ $scan['right_eye']['sph'] ?? '—' }}</span>
                            <span>CYL {{ $scan['right_eye']['cyl'] ?? '—' }}</span>
                            <span>AXIS {{ $scan['right_eye']['axis'] ?? '—' }}</span>
                            @if($visionType === 'progressive')
                                <span>ADD {{ $scan['right_eye']['add'] ?? '—' }}</span>
                            @endif
                        </div>
                        <div class="rx-eye">
                            <strong>OS (Left)</strong>
                            <span>SPH {{ $scan['left_eye']['sph'] ?? '—' }}</span>
                            <span>CYL {{ $scan['left_eye']['cyl'] ?? '—' }}</span>
                            <span>AXIS {{ $scan['left_eye']['axis'] ?? '—' }}</span>
                            @if($visionType === 'progressive')
                                <span>ADD {{ $scan['left_eye']['add'] ?? '—' }}</span>
                            @endif
                        </div>
                        @php $pd = $scan['pd'] ?? []; @endphp
                        <div class="rx-pd">
                            <strong>PD</strong>
                            @if(($pd['type'] ?? '') === 'one')
                                <span>{{ $pd['one'] ?? '—' }}</span>
                            @elseif(($pd['type'] ?? '') === 'two')
                                <span>{{ $pd['right'] ?? '?' }} / {{ $pd['left'] ?? '?' }}</span>
                            @else
                                <span>—</span>
                            @endif
                        </div>
                        @if(!empty($scan['notes']))
                            <p class="rx-notes">{{ $scan['notes'] }}</p>
                        @endif
                    </div>
                @endif
            @else
                <label class="upload" id="rx-upload-label">
                <input type="file" accept="image/*" id="rx-file">
                    <span id="rx-upload-text">Scan / upload prescription</span>
                </label>
            @endif
        </section>

        <section class="block">
            <h2 class="block-title">Non-Prescription</h2>
            <p class="block-sub"></p>
            <div class="addon-list" id="lens-list">
                <label class="addon">
                    <input type="radio" name="lens_coating_id" value=""
                        @checked(empty($cart['lens_coating_id'])) data-price="0" data-label="None">
                    <span class="addon-body">
                        <strong>No coating</strong>
                        <em>+ {{ $currency }} 0.00</em>
                    </span>
                </label>
                @foreach($lensCoatings as $coating)
                    <label class="addon">
                        <input type="radio" name="lens_coating_id" value="{{ $coating['id'] }}"
                            @checked((int)($cart['lens_coating_id'] ?? 0) === (int)$coating['id'])
                            data-price="{{ $coating['price'] }}"
                            data-label="{{ $coating['label'] }}">
                        <span class="addon-body">
                            <strong>{{ $coating['label'] }}</strong>
                            <em>+ {{ $currency }} {{ number_format($coating['price'], 2) }}</em>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="totals card" id="totals"
            data-subtotal="{{ $totals['subtotal'] }}"
            data-prescription="{{ !empty($cart['prescription']['scanned']) ? $totals['prescription'] : 0 }}"
            data-currency="{{ $currency }}">
            <div class="tot-row"><span>Frame</span><span id="tot-sub">{{ $currency }} {{ number_format($totals['subtotal'], 2) }}</span></div>
            <div class="tot-row" id="row-rx" @if(empty($cart['prescription']['scanned'])) style="display:none" @endif>
                <span id="rx-tot-label">
                    @if(($cart['prescription']['price_mode'] ?? '') === 'range')
                        Prescription (est.)
                    @elseif(($cart['prescription']['price_mode'] ?? '') === 'quote')
                        Prescription (lab quote)
                    @else
                        Prescription
                    @endif
                </span>
                <span id="tot-rx">
                    @if(($cart['prescription']['price_mode'] ?? '') === 'quote')
                        TBD
                    @else
                        {{ $currency }} {{ number_format($totals['prescription'], 2) }}
                    @endif
                </span>
            </div>
            <div class="tot-row" id="row-lens" @if($totals['lens'] <= 0) style="display:none" @endif>
                <span id="lens-label">Non-Prescription</span><span id="tot-lens">{{ $currency }} {{ number_format($totals['lens'], 2) }}</span>
            </div>
            <div class="tot-row tot-grand"><span>Total</span><span id="tot-grand">{{ $currency }} {{ number_format($totals['total'], 2) }}</span></div>
        </section>

        <div class="cart-dock-spacer" aria-hidden="true"></div>

        <div class="dock cart-dock" id="cart-dock">
            <div class="dock-total" id="dock-total">
                <span>Running total</span>
                <strong id="dock-grand">{{ $currency }} {{ number_format($totals['total'], 2) }}</strong>
            </div>
            <button type="submit" class="btn btn-accent">Continue to payment</button>
            <a href="{{ route('shop.frames') }}" class="btn btn-ghost keep-browsing">Keep browsing</a>
        </div>
    </form>

    <form id="rx-form" method="post" action="{{ route('shop.cart.prescription') }}" enctype="multipart/form-data" hidden>
        @csrf
        <input type="file" name="prescription" id="rx-form-file" accept="image/*">
    </form>
    <form id="clear-rx" method="post" action="{{ route('shop.cart.prescription.clear') }}" hidden>
        @csrf
        @method('DELETE')
    </form>

    @push('head')
    <style>
        .cart-items { padding: 0.25rem 0; margin-bottom: 1.25rem; }
        .cart-row {
            display: grid; grid-template-columns: 3.5rem 1fr auto; gap: 0.75rem;
            align-items: center; padding: 0.85rem 1rem; border-bottom: 1px solid var(--line);
        }
        .cart-row:last-child { border-bottom: 0; }
        .cart-thumb { width: 3.5rem; height: 3.5rem; border-radius: 0.75rem; background: #e8eefc center/cover no-repeat; }
        .cart-meta { display:flex; flex-direction:column; gap:0.15rem; font-size:0.9rem; }
        .cart-meta span { color: var(--ink-soft); font-size:0.8rem; }
        .link-danger { border:0; background:transparent; color: var(--danger); font-size:1rem; cursor:pointer; padding:0.35rem; }
        .block { margin-bottom: 1.35rem; }
        .block-title { font-size: 1.2rem; margin: 0 0 0.25rem; font-weight: 700; color: var(--ink); }
        .block-sub { margin: 0 0 0.85rem; color: var(--ink-soft); font-size: 0.875rem; }
        .upload {
            display:flex; align-items:center; justify-content:center; min-height: 5.5rem;
            border: 1.5px dashed rgba(29,78,216,0.4); border-radius: var(--radius);
            background: var(--accent-soft); color: #1e3a8a; font-weight: 700; cursor: pointer;
            transition: transform 0.15s ease;
        }
        .upload:active { transform: scale(0.99); }
        .upload input { display:none; }
        .rx-ok {
            display:flex; justify-content:space-between; align-items:center; gap: 0.75rem;
            padding: 0.9rem 1rem; border-radius: var(--radius); background: var(--accent-soft);
            color: #1e3a8a; font-weight: 700; border: 1px solid var(--accent-border);
        }
        .rx-ok-main { display:flex; flex-direction:column; gap:0.15rem; }
        .rx-ok-main small { font-weight: 500; font-size: 0.8rem; opacity: 0.85; }
        .rx-ok a { color: inherit; text-decoration: underline; font-weight: 500; font-size: 0.85rem; flex-shrink:0; }
        .rx-price-note {
            margin: 0.55rem 0 0;
            font-size: 0.8rem;
            color: var(--ink-soft);
            line-height: 1.45;
        }
        .rx-values {
            margin-top: 0.65rem; padding: 0.9rem 1rem;
            display: grid; gap: 0.65rem;
        }
        .rx-type-row {
            display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;
            font-size: 0.85rem;
        }
        .rx-type-row strong { min-width: 5.5rem; color: #1e3a8a; }
        .rx-type-badge {
            display: inline-flex; align-items: center;
            padding: 0.25rem 0.7rem; border-radius: 999px;
            font-size: 0.75rem; font-weight: 700;
        }
        .rx-type-badge.is-single {
            background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;
        }
        .rx-type-badge.is-progressive {
            background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;
        }
        .rx-eye, .rx-pd {
            display: flex; flex-wrap: wrap; gap: 0.45rem 0.85rem;
            font-size: 0.85rem; color: var(--ink);
        }
        .rx-eye strong, .rx-pd strong { min-width: 5.5rem; color: #1e3a8a; }
        .rx-notes {
            margin: 0.25rem 0 0; font-size: 0.8rem; color: var(--ink-soft);
        }
        .upload.is-busy { opacity: 0.7; pointer-events: none; }
        .addon-list { display:grid; gap:0.55rem; }
        .addon {
            display:flex; align-items:center; gap:0.75rem; padding: 0.9rem 1rem;
            border-radius: 1rem; background: #fff; border: 1px solid var(--line); cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }
        .addon:has(input:checked) {
            border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); animation: pop 0.28s ease;
            background: var(--accent-soft);
        }
        .addon input { width: 1.1rem; height: 1.1rem; accent-color: var(--accent); }
        .addon-body { display:flex; justify-content:space-between; flex:1; gap:0.5rem; }
        .addon-body em { font-style:normal; color: var(--ink-soft); font-size:0.85rem; font-weight: 700; }
        .totals { padding: 1rem 1.1rem; margin-bottom: 2.5rem; }
        .tot-row { display:flex; justify-content:space-between; margin-bottom:0.45rem; font-size:0.9rem; }
        .tot-grand { margin-top:0.55rem; padding-top:0.55rem; border-top:1px solid var(--line); font-weight:700; font-size:1.05rem; color: var(--accent); }
        #tot-grand { transition: transform 0.2s ease; }
        #tot-grand.is-bump { animation: pop 0.3s ease; }
        .keep-browsing { margin-top: 0.55rem; }
        .cart-dock-spacer { height: calc(9.5rem + var(--safe-b)); }
        .dock-total {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 0.65rem; padding: 0.75rem 1rem;
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 1rem;
            font-size: 0.9rem; color: #1e3a8a;
        }
        .dock-total strong {
            font-size: 1.25rem; font-weight: 700; color: #1e40af;
            transition: transform 0.2s ease;
        }
        .dock-total strong.is-bump { animation: pop 0.3s ease; }
        .cart-dock {
            background: linear-gradient(to top, var(--bg) 78%, transparent);
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        (function () {
            var file = document.getElementById('rx-file');
            if (file) {
                file.addEventListener('change', function () {
                    if (!file.files || !file.files.length) return;
                    var dest = document.getElementById('rx-form-file');
                    var dt = new DataTransfer();
                    dt.items.add(file.files[0]);
                    dest.files = dt.files;
                    var label = document.getElementById('rx-upload-label');
                    var text = document.getElementById('rx-upload-text');
                    if (label) label.classList.add('is-busy');
                    if (text) text.textContent = 'Scanning prescription…';
                    document.getElementById('rx-form').submit();
                });
            }

            var box = document.getElementById('totals');
            var subtotal = parseFloat(box.dataset.subtotal || '0');
            var prescription = parseFloat(box.dataset.prescription || '0');
            var currency = box.dataset.currency || 'ETB';

            function money(n) {
                return currency + ' ' + (Math.round(n * 100) / 100).toFixed(2);
            }

            function bump(el) {
                if (!el) return;
                el.classList.remove('is-bump');
                void el.offsetWidth;
                el.classList.add('is-bump');
            }

            function refresh() {
                var checked = document.querySelector('input[name="lens_coating_id"]:checked');
                var lens = checked ? parseFloat(checked.dataset.price || '0') : 0;
                var label = checked && checked.dataset.label ? checked.dataset.label : 'Non-Prescription';
                var rowLens = document.getElementById('row-lens');
                if (lens > 0) {
                    rowLens.style.display = '';
                    document.getElementById('lens-label').textContent = label === 'None' ? 'Non-Prescription' : label;
                    document.getElementById('tot-lens').textContent = money(lens);
                } else {
                    rowLens.style.display = 'none';
                }
                var grand = subtotal + prescription + lens;
                var el = document.getElementById('tot-grand');
                el.textContent = money(grand);
                bump(el);
                var dockGrand = document.getElementById('dock-grand');
                if (dockGrand) {
                    dockGrand.textContent = money(grand);
                    bump(dockGrand);
                }
            }

            document.querySelectorAll('input[name="lens_coating_id"]').forEach(function (r) {
                r.addEventListener('change', refresh);
            });
            refresh();
        })();
    </script>
    @endpush
@endsection
