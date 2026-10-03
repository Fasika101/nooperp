@extends('shop.layout')

@section('title', 'Choose gender — '.$brand)

@section('content')
    <div class="gender-stage anim-in">
        <h1 class="page-title">Who are we shopping for?</h1>
        <p class="page-sub">Tap a category to see matching frames in stock.</p>

        <div class="gender-grid">
            @forelse($genders as $i => $gender)
                <a href="{{ route('shop.gender.select', $gender->id) }}"
                   class="gender-card"
                   style="animation-delay: {{ $i * 0.04 }}s">
                    <span class="gender-name">{{ $gender->name }}</span>
                    <span class="gender-go">Browse frames →</span>
                </a>
            @empty
                <p class="page-sub">No gender options configured in the ERP yet.</p>
            @endforelse
        </div>
    </div>

    @push('head')
    <style>
        .gender-stage {
            min-height: auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding-top: 0.5rem;
        }
        .gender-grid { display: grid; gap: 0.85rem; }
        .gender-card {
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 7.25rem; padding: 1.35rem 1.4rem; border-radius: var(--radius);
            background: linear-gradient(145deg, var(--accent-soft), #fff 55%);
            box-shadow: var(--shadow); border: 1px solid var(--line);
            animation: fadeUp 0.35s ease both;
            transition: transform 0.1s ease, box-shadow 0.1s ease, border-color 0.1s ease, background 0.1s ease;
            touch-action: manipulation;
            cursor: pointer;
            -webkit-tap-highlight-color: rgba(37, 99, 235, 0.18);
        }
        .gender-card:hover {
            border-color: var(--accent-2);
            box-shadow: 0 14px 32px rgba(29,78,216,0.16);
        }
        .gender-card:active,
        .gender-card.is-pressing {
            transform: scale(0.97);
            background: #dbeafe;
            border-color: #93c5fd;
            box-shadow: 0 4px 12px rgba(29,78,216,0.12);
        }
        .gender-card:nth-child(even) {
            background: linear-gradient(145deg, #eff6ff, #fff 60%);
        }
        .gender-card:nth-child(even):active,
        .gender-card:nth-child(even).is-pressing {
            background: #dbeafe;
        }
        .gender-name { font-size: 1.55rem; font-weight: 700; letter-spacing: -0.02em; color: var(--ink); pointer-events: none; }
        .gender-go { font-size: 0.8125rem; font-weight: 700; color: var(--accent); pointer-events: none; }
    </style>
    @endpush

    @push('scripts')
    <script>
        (function () {
            document.querySelectorAll('.gender-card').forEach(function (card) {
                var press = function () { card.classList.add('is-pressing'); };
                var release = function () { card.classList.remove('is-pressing'); };
                card.addEventListener('pointerdown', press);
                card.addEventListener('pointerup', release);
                card.addEventListener('pointercancel', release);
                card.addEventListener('pointerleave', release);
                // Navigate immediately on tap — no 300ms delay feel in Telegram WebView
                card.addEventListener('click', function () {
                    card.classList.add('is-pressing');
                }, { capture: true });
            });
        })();
    </script>
    @endpush
@endsection
