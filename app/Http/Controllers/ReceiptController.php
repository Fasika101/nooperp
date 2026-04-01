<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show(Order $order)
    {
        $order->load(['orderItems.product', 'customer', 'taxType']);
        $currency = Setting::getDefaultCurrency();
        return view('receipt', compact('order', 'currency'));
    }

    public function pdf(Order $order)
    {
        $order->load(['orderItems.product', 'customer', 'taxType']);
        $currency = Setting::getDefaultCurrency();
        $forPdf = true;
        $pdf = Pdf::loadView('receipt', compact('order', 'currency', 'forPdf'));
        return $pdf->download("receipt-{$order->id}.pdf");
    }
}
