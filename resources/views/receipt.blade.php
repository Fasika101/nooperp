<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $order->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; font-size: 14px; line-height: 1.4; color: #333; max-width: 400px; margin: 0 auto; padding: 20px; }
        .receipt-header { text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px dashed #333; }
        .receipt-header h1 { font-size: 20px; margin-bottom: 8px; }
        .receipt-header .business-info { font-size: 12px; color: #666; }
        .receipt-header .business-info p { margin: 2px 0; }
        .receipt-body { margin-bottom: 20px; }
        .receipt-body table { width: 100%; border-collapse: collapse; }
        .receipt-body th, .receipt-body td { padding: 6px 0; text-align: left; border-bottom: 1px solid #eee; }
        .receipt-body th { font-size: 11px; color: #666; text-transform: uppercase; }
        .receipt-body .total-row { font-weight: bold; font-size: 16px; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
        .receipt-footer { text-align: center; font-size: 12px; color: #666; padding-top: 15px; border-top: 2px dashed #333; }
        .receipt-footer p { margin: 4px 0; }
        .text-right { text-align: right; }
        .receipt-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .receipt-row.total { font-weight: bold; font-size: 16px; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
        @media print { body { padding: 10px; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="receipt-header">
        <h1>{{ config('app.name') }}</h1>
        <div class="business-info">
            @if($businessPhone = \App\Models\Setting::getBusinessPhone())
                <p>Tel: {{ $businessPhone }}</p>
            @endif
            @if($businessEmail = \App\Models\Setting::getBusinessEmail())
                <p>Email: {{ $businessEmail }}</p>
            @endif
            @if($businessTin = \App\Models\Setting::getBusinessTin())
                <p>TIN: {{ $businessTin }}</p>
            @endif
        </div>
    </div>

    <div class="receipt-body">
        <p><strong>Receipt #{{ $order->id }}</strong></p>
        <p>{{ $order->created_at->format('M d, Y H:i') }}</p>
        <p><strong>Customer:</strong> {{ $order->customer->name }}</p>
        @if($order->customer->phone)
            <p>Phone: {{ $order->customer->phone }}</p>
        @endif

        <table style="margin-top: 15px;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderItems as $item)
                <tr>
                    <td>
                        {{ $item->display_name }}
                        @if($item->hasOpticalDetails())
                            @php $m = $item->optical_meta; @endphp
                            <div style="font-size: 10px; color: #666; margin-top: 4px; line-height: 1.35;">
                                @if(($m['route'] ?? '') === 'prescription')
                                    OD: sph {{ $m['od']['sph'] ?? '—' }}, cyl {{ $m['od']['cyl'] ?? '—' }}, axis {{ $m['od']['axis'] ?? '—' }}@if(($m['vision'] ?? '') === 'progressive'), add {{ $m['od']['add'] ?? '—' }}@endif
                                    <br>
                                    OS: sph {{ $m['os']['sph'] ?? '—' }}, cyl {{ $m['os']['cyl'] ?? '—' }}, axis {{ $m['os']['axis'] ?? '—' }}@if(($m['vision'] ?? '') === 'progressive'), add {{ $m['os']['add'] ?? '—' }}@endif
                                    @if(!empty($m['pd']['mode']))
                                        <br>PD @if($m['pd']['mode'] === 'one') {{ $m['pd']['single'] ?? '—' }} @else R {{ $m['pd']['right'] ?? '—' }} / L {{ $m['pd']['left'] ?? '—' }} @endif
                                    @endif
                                @endif
                                @if(!empty($m['frame']) && (filled($m['frame']['size_name'] ?? null) || filled($m['frame']['color_name'] ?? null)))
                                    <br>Frame: {{ collect([$m['frame']['size_name'] ?? null, $m['frame']['color_name'] ?? null])->filter()->implode(', ') }}
                                @endif
                            </div>
                        @endif
                    </td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ \Illuminate\Support\Number::currency($item->price, $currency) }}</td>
                    <td class="text-right">{{ \Illuminate\Support\Number::currency($item->price * $item->quantity, $currency) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 15px;">
            <div class="receipt-row">
                <span>Subtotal</span>
                <span>{{ \Illuminate\Support\Number::currency($order->orderItems->sum(fn($i) => $i->price * $i->quantity), $currency) }}</span>
            </div>
            @if($order->discount_amount > 0)
                <div class="receipt-row">
                    <span>Discount</span>
                    <span>-{{ \Illuminate\Support\Number::currency($order->discount_amount, $currency) }}</span>
                </div>
            @endif
            @if($order->shipping_amount > 0)
                <div class="receipt-row">
                    <span>Shipping</span>
                    <span>{{ \Illuminate\Support\Number::currency($order->shipping_amount, $currency) }}</span>
                </div>
            @endif
            @if($order->tax_amount > 0)
                <div class="receipt-row">
                    <span>Tax{{ $order->taxType ? ' (' . $order->taxType->name . ')' : '' }}</span>
                    <span>{{ \Illuminate\Support\Number::currency($order->tax_amount, $currency) }}</span>
                </div>
            @endif
            <div class="receipt-row total">
                <span>Total</span>
                <span>{{ \Illuminate\Support\Number::currency($order->total_amount, $currency) }}</span>
            </div>
        </div>
    </div>

    <div class="receipt-footer">
        <p>Thank you for your business!</p>
    </div>

    @if(!isset($forPdf) || !$forPdf)
    <div class="no-print" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-size: 14px;">Print</button>
        <a href="{{ route('receipt.pdf', $order) }}" target="_blank" style="padding: 10px 20px; background: #333; color: white; text-decoration: none; font-size: 14px; border-radius: 4px;">Download PDF</a>
    </div>
    @endif
</body>
</html>
