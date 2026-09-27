@extends('shop.layout')

@section('title', ($paid ?? false) ? 'Payment successful — '.$brand : 'Order — '.$brand)

@section('content')
    @if($paid ?? false)
        <p class="page-kicker anim-in">Payment</p>
        <h1 class="page-title anim-in">Payment successful</h1>
        <p class="page-sub anim-in">Thank you. Your payment was received and your order is in our system.</p>
    @else
        <p class="page-kicker anim-in">Payment</p>
        <h1 class="page-title anim-in">Confirming payment…</h1>
        <p class="page-sub anim-in">
            @if($ref)
                We’re verifying <strong>{{ $ref }}</strong> with Chapa. Refresh in a moment if details don’t appear yet.
            @else
                No payment reference was provided.
            @endif
        </p>
    @endif

    @if($order)
        <div class="card success-card anim-in" style="padding:1.15rem;">
            <p style="margin:0 0 0.35rem;color:var(--ink-soft);font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Transaction reference</p>
            <p style="margin:0 0 1rem;font-weight:700;font-size:1.15rem;letter-spacing:0.02em;">{{ $order->external_ref }}</p>

            <p style="margin:0 0 0.35rem;color:var(--ink-soft);font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Amount paid</p>
            <p style="margin:0 0 1rem;font-weight:700;font-size:1.35rem;color:var(--accent);">{{ $currency }} {{ number_format($order->total_amount, 2) }}</p>

            @if($order->customer)
                <p style="margin:0 0 0.35rem;color:var(--ink-soft);font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Customer</p>
                <p style="margin:0;font-weight:600;">{{ $order->customer->name }}</p>
                @if($order->customer->phone)
                    <p style="margin:0.25rem 0 0;color:var(--ink-soft);font-size:0.9rem;">{{ $order->customer->phone }}</p>
                @endif
            @endif
        </div>
        <p class="page-sub anim-in" style="margin-top:1rem;">
            A payment receipt is sent to your Telegram (if you’ve messaged our bot) and a short SMS with the reference and amount is sent to your phone.
        </p>
    @elseif($ref)
        <div class="card anim-in" style="padding:1.15rem;">
            <p style="margin:0 0 0.35rem;color:var(--ink-soft);font-size:0.8rem;">Reference</p>
            <p style="margin:0;font-weight:700;">{{ $ref }}</p>
        </div>
    @endif

    <div class="dock">
        <a href="{{ route('shop.gender') }}" class="btn btn-primary">Shop again</a>
    </div>
@endsection
