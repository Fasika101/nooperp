@extends('shop.layout')

@section('title', 'Checkout — '.$brand)

@section('content')
    <h1 class="page-title anim-in">Delivery &amp; pay</h1>
    <p class="page-sub anim-in">Confirm your details, then pay or keep browsing.</p>

    <section class="totals card anim-in" id="totals">
        <div class="tot-row"><span>Frame</span><span>{{ $currency }} {{ number_format($totals['subtotal'], 2) }}</span></div>
        @if(!empty($cart['prescription']['scanned']))
            <div class="tot-row">
                <span>
                    @if(($cart['prescription']['price_mode'] ?? '') === 'range')
                        Prescription (est.)
                    @elseif(($cart['prescription']['price_mode'] ?? '') === 'quote')
                        Prescription (lab quote)
                    @else
                        Prescription
                    @endif
                </span>
                <span>
                    @if(($cart['prescription']['price_mode'] ?? '') === 'quote')
                        TBD
                    @else
                        {{ $currency }} {{ number_format($totals['prescription'], 2) }}
                    @endif
                </span>
            </div>
        @endif
        @if($totals['lens'] > 0)
            <div class="tot-row"><span>Non-Prescription</span><span>{{ $currency }} {{ number_format($totals['lens'], 2) }}</span></div>
        @endif
        <div class="tot-row tot-grand"><span>Total</span><span>{{ $currency }} {{ number_format($totals['total'], 2) }}</span></div>
    </section>

    <form method="post" action="{{ route('shop.checkout.pay') }}" id="checkout-form" class="anim-in">
        @csrf

        <section class="block delivery-block">
            <h2 class="block-title">Delivery details</h2>
            <div class="field">
                <label for="name">Name *</label>
                <input id="name" name="name" required value="{{ old('name', $cart['customer']['name'] ?? '') }}" autocomplete="name">
            </div>
            <div class="field">
                <label for="phone_local">Phone *</label>
                @php
                    $phoneRaw = old('phone', $cart['customer']['phone'] ?? '');
                    $phoneLocal = preg_replace('/\D+/', '', (string) $phoneRaw);
                    if (str_starts_with($phoneLocal, '251')) {
                        $phoneLocal = substr($phoneLocal, 3);
                    } elseif (str_starts_with($phoneLocal, '0')) {
                        $phoneLocal = substr($phoneLocal, 1);
                    }
                    $phoneLocal = substr($phoneLocal, 0, 9);
                @endphp
                <div class="phone-row">
                    <span class="phone-prefix" aria-hidden="true">+251</span>
                    <input
                        id="phone_local"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        maxlength="9"
                        pattern="[79][0-9]{8}"
                        placeholder="9XXXXXXXX"
                        required
                        value="{{ $phoneLocal }}"
                        aria-describedby="phone-hint"
                    >
                </div>
                <input type="hidden" name="phone" id="phone" value="{{ $phoneLocal !== '' ? '+251'.$phoneLocal : '' }}">
                <p class="field-hint" id="phone-hint">Ethiopian mobile: 9 digits after +251 (e.g. 912345678)</p>
            </div>
            <div class="field">
                <label for="address">Address *</label>
                <textarea id="address" name="address" rows="3" required>{{ old('address', $cart['customer']['address'] ?? '') }}</textarea>
            </div>
        </section>

        <div class="cart-dock-spacer" aria-hidden="true"></div>

        <div class="dock cart-dock" id="cart-dock">
            <div class="dock-total" id="dock-total">
                <span>Total to pay</span>
                <strong id="dock-grand">{{ $currency }} {{ number_format($totals['total'], 2) }}</strong>
            </div>
            <button type="submit" class="btn btn-accent" @disabled(! $chapaReady)>
                Pay with Chapa
            </button>
            @unless($chapaReady)
                <p class="field-hint" style="text-align:center;margin-top:0.5rem;">Payment gateway keys are not set yet.</p>
            @endunless
            <a href="{{ route('shop.frames') }}" class="btn btn-ghost keep-browsing">Keep browsing</a>
        </div>
    </form>

    @push('head')
    <style>
        .block { margin-bottom: 1.35rem; }
        .block-title { font-size: 1.2rem; margin: 0 0 0.85rem; font-weight: 700; color: var(--ink); }
        .totals { padding: 1rem 1.1rem; margin-bottom: 1.25rem; }
        .tot-row { display:flex; justify-content:space-between; margin-bottom:0.45rem; font-size:0.9rem; }
        .tot-grand { margin-top:0.55rem; padding-top:0.55rem; border-top:1px solid var(--line); font-weight:700; font-size:1.05rem; color: var(--accent); }
        .keep-browsing { margin-top: 0.55rem; }
        .cart-dock-spacer { height: calc(11rem + var(--safe-b)); }
        .delivery-block .field input,
        .delivery-block .field textarea {
            scroll-margin-bottom: calc(12rem + var(--safe-b));
        }
        .phone-row {
            display: flex; align-items: stretch; gap: 0;
            border: 1px solid var(--line); background: var(--bg-elevated);
            border-radius: 0.9rem; overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .phone-row:focus-within {
            border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .phone-prefix {
            display: flex; align-items: center; padding: 0 0.85rem;
            background: var(--accent-soft); color: #1e40af; font-weight: 700; font-size: 0.95rem;
            border-right: 1px solid var(--line); user-select: none;
        }
        .phone-row input {
            border: 0 !important; box-shadow: none !important; border-radius: 0 !important;
            flex: 1; min-width: 0; letter-spacing: 0.04em;
        }
        .field-hint {
            margin: 0.4rem 0 0; font-size: 0.75rem; color: var(--ink-soft);
        }
        .dock-total {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 0.65rem; padding: 0.75rem 1rem;
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 1rem;
            font-size: 0.9rem; color: #1e3a8a;
        }
        .dock-total strong {
            font-size: 1.25rem; font-weight: 700; color: #1e40af;
        }
        .cart-dock {
            background: linear-gradient(to top, var(--bg) 78%, transparent);
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        (function () {
            var phoneLocal = document.getElementById('phone_local');
            var phoneHidden = document.getElementById('phone');
            function syncPhone() {
                if (!phoneLocal || !phoneHidden) return;
                var digits = (phoneLocal.value || '').replace(/\D+/g, '').slice(0, 9);
                if (digits.length && digits.charAt(0) === '0') {
                    digits = digits.slice(1).slice(0, 9);
                }
                phoneLocal.value = digits;
                phoneHidden.value = digits.length === 9 ? ('+251' + digits) : '';
            }
            if (phoneLocal) {
                phoneLocal.addEventListener('input', syncPhone);
                phoneLocal.addEventListener('blur', syncPhone);
                syncPhone();
            }

            var form = document.getElementById('checkout-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    syncPhone();
                    if (!phoneHidden.value || !/^\+251[79]\d{8}$/.test(phoneHidden.value)) {
                        e.preventDefault();
                        if (phoneLocal) {
                            phoneLocal.focus();
                            phoneLocal.setCustomValidity('Enter a valid Ethiopian mobile: 9 digits starting with 9 or 7');
                            phoneLocal.reportValidity();
                            phoneLocal.setCustomValidity('');
                        }
                    }
                });
            }
        })();
    </script>
    @endpush
@endsection
