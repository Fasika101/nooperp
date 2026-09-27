@extends('shop.layout')

@section('title', 'Choose gender — '.$brand)

@section('content')
    <div class="gender-stage anim-in">
        <p class="page-kicker">Step 1</p>
        <h1 class="page-title">Who are we shopping for?</h1>
        <p class="page-sub">Pick a gender to see matching frames in stock.</p>

        <div class="gender-grid">
            @forelse($genders as $i => $gender)
                <a href="{{ route('shop.gender.select', $gender->id) }}" class="gender-card" style="animation-delay: {{ $i * 0.06 }}s">
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
            min-height: calc(100dvh - 8rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .gender-grid { display: grid; gap: 0.85rem; }
        .gender-card {
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 6.5rem; padding: 1.25rem 1.35rem; border-radius: var(--radius);
            background: linear-gradient(145deg, var(--accent-soft), #fff 55%);
            box-shadow: var(--shadow); border: 1px solid var(--line);
            animation: fadeUp 0.45s ease both;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .gender-card:active, .gender-card:hover {
            transform: translateY(-2px) scale(1.01);
            border-color: var(--accent-2);
            box-shadow: 0 14px 32px rgba(29,78,216,0.16);
        }
        .gender-card:nth-child(even) {
            background: linear-gradient(145deg, #eff6ff, #fff 60%);
        }
        .gender-name { font-size: 1.55rem; font-weight: 700; letter-spacing: -0.02em; color: var(--ink); }
        .gender-go { font-size: 0.8125rem; font-weight: 700; color: var(--accent); }
    </style>
    @endpush
@endsection
