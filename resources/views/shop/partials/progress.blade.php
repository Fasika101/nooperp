@php
    $step = (int) ($shopStep ?? 0);
    $milestones = [
        1 => ['key' => 'gender', 'label' => 'Gender', 'icon' => 'person'],
        2 => ['key' => 'frames', 'label' => 'Frames', 'icon' => 'glasses'],
        3 => ['key' => 'cart', 'label' => 'Cart', 'icon' => 'cart'],
        4 => ['key' => 'checkout', 'label' => 'Details', 'icon' => 'pin'],
        5 => ['key' => 'done', 'label' => 'Done', 'icon' => 'check'],
    ];
    $fillPct = $step <= 1 ? 0 : (($step - 1) / (count($milestones) - 1)) * 100;
@endphp

@if($step >= 1)
<nav class="shop-progress" aria-label="Order progress">
    <div class="shop-progress-track" aria-hidden="true">
        <div class="shop-progress-fill" style="width: {{ $fillPct }}%;"></div>
    </div>
    <ol class="shop-progress-steps">
        @foreach($milestones as $n => $m)
            @php
                $state = $n < $step ? 'is-done' : ($n === $step ? 'is-current' : 'is-todo');
            @endphp
            <li class="shop-progress-step {{ $state }}">
                <span class="shop-progress-dot" title="{{ $m['label'] }}">
                    @if($m['icon'] === 'person')
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 19c1.5-3.5 4-5 7-5s5.5 1.5 7 5"/></svg>
                    @elseif($m['icon'] === 'glasses')
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="7.5" cy="13" r="3.5"/><circle cx="16.5" cy="13" r="3.5"/><path d="M11 13h2M4 13H2.5M22 13h-1.5"/></svg>
                    @elseif($m['icon'] === 'cart')
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h2l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L21 8H7"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
                    @elseif($m['icon'] === 'pin')
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z"/><circle cx="12" cy="11" r="2.2"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12.5 10 17.5 19 7.5"/></svg>
                    @endif
                </span>
                <span class="shop-progress-label">{{ $m['label'] }}</span>
            </li>
        @endforeach
    </ol>
</nav>
@endif
