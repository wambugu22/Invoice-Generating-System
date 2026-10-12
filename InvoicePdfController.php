<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    /**
     * Preview / Stream the invoice PDF directly in the browser.
     */
    public function stream(Invoice $invoice): Response
    {
        $pdf = $this->generatePdf($invoice);
        $filename = ($invoice->invoice_number ?: 'Draft-Invoice-'.$invoice->id).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Force download the invoice PDF.
     */
    public function download(Invoice $invoice): Response
    {
        $pdf = $this->generatePdf($invoice);
        $filename = ($invoice->invoice_number ?: 'Draft-Invoice-'.$invoice->id).'.pdf';

        return $pdf->download($filename);
    }

    protected function generatePdf(Invoice $invoice)
    {
        $invoice->load(['items', 'customer', 'payments']);
        $settings = BusinessSetting::current();

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $settings,
        ])->setPaper('a4', 'portrait');
    }
}
