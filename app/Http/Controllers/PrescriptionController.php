<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class PrescriptionController extends Controller
{
    public function print(Prescription $prescription)
    {
        $prescription->load(['customer', 'orderItem.order']);
        $businessName = Setting::getBusinessName();
        $businessAddress = Setting::getBusinessAddress();
        $businessPhone = Setting::getBusinessPhone();
        $businessEmail = Setting::getBusinessEmail();

        return view('prescription-print', compact(
            'prescription',
            'businessName',
            'businessAddress',
            'businessPhone',
            'businessEmail'
        ));
    }

    public function pdf(Prescription $prescription)
    {
        $prescription->load(['customer', 'orderItem.order']);
        $businessName = Setting::getBusinessName();
        $businessAddress = Setting::getBusinessAddress();
        $businessPhone = Setting::getBusinessPhone();
        $businessEmail = Setting::getBusinessEmail();

        $pdf = Pdf::loadView('prescription-print', compact(
            'prescription',
            'businessName',
            'businessAddress',
            'businessPhone',
            'businessEmail'
        ));

        return $pdf->download("prescription-{$prescription->id}.pdf");
    }
}
