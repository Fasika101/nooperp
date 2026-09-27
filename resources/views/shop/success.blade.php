@extends('shop.layout')

@section('title', 'Order confirmed — '.$brand)

@section('content')
    <p class="page-kicker">Done</p>
    <h1 class="page-title">Thank you</h1>
    @if($order)
        <p class="page-sub">Your order is in. We’ll confirm delivery on the phone you shared.</p>
        <div class="card" style="padding:1.15rem;">
            <p style="margin:0 0 0.35rem;color:var(--ink-soft);font-size:0.8rem;">Reference</p>
            <p style="margin:0 0 0.85rem;font-weight:700;">{{ $order->external_ref }}</p>
            <p style="margin:0 0 0.35rem;color:var(--ink-soft);font-size:0.8rem;">Total</p>
            <p style="margin:0;font-weight:700;font-size:1.25rem;">{{ $currency }} {{ number_format($order->total_amount, 2) }}</p>
        </div>
    @else
        <p class="page-sub">
            @if($ref)
                Payment received for <strong>{{ $ref }}</strong>. If details don’t show yet, refresh in a moment — we’re confirming with the bank.
            @else
                Your order is being confirmed.
            @endif
        </p>
    @endif

    <div class="dock">
        <a href="{{ route('shop.gender') }}" class="btn btn-primary">Shop again</a>
    </div>
@endsection
