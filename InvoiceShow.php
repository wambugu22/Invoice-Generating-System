<?php

namespace App\Livewire\Invoices;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Livewire\Component;

class InvoiceShow extends Component
{
    public Invoice $invoice;

    // Payment recording modal state
    public bool $showPaymentModal = false;

    public string $payment_amount = '';

    public string $payment_date = '';

    public string $payment_method = 'M-Pesa'; // Cash, Bank Transfer, M-Pesa, Other

    public ?string $payment_reference = '';

    public ?string $payment_notes = '';

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->invoice->load(['items', 'payments', 'customer']);
        $this->payment_date = Carbon::today()->format('Y-m-d');
        $this->payment_amount = $this->invoice->balance_due;
    }

    public function openPaymentModal()
    {
        $this->payment_amount = $this->invoice->balance_due;
        $this->payment_date = Carbon::today()->format('Y-m-d');
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
    }

    public function recordPayment(InvoiceService $service)
    {
        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01|max:'.((float) $this->invoice->balance_due + 0.01),
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:30',
            'payment_reference' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string|max:500',
        ]);

        $service->recordPayment(
            invoice: $this->invoice,
            amount: (float) $this->payment_amount,
            paymentDate: $this->payment_date,
            method: $this->payment_method,
            reference: $this->payment_reference,
            notes: $this->payment_notes
        );

        $this->invoice = $this->invoice->fresh(['items', 'payments', 'customer']);
        $this->showPaymentModal = false;
        $this->payment_reference = '';
        $this->payment_notes = '';

        session()->flash('success', 'Payment recorded successfully.');
    }

    public function finalize(InvoiceService $service)
    {
        $this->invoice = $service->finalizeInvoice($this->invoice);
        session()->flash('success', "Invoice finalized as {$this->invoice->invoice_number}.");
    }

    public function voidInvoice(InvoiceService $service)
    {
        $this->invoice = $service->voidInvoice($this->invoice, 'Voided from view screen');
        session()->flash('success', 'Invoice has been marked as void.');
    }

    public function duplicate(InvoiceService $service)
    {
        $newDraft = $service->duplicateInvoice($this->invoice);
        session()->flash('success', "Invoice duplicated as Draft #{$newDraft->id}.");

        return redirect()->route('invoices.edit', $newDraft);
    }

    public function deleteInvoice(InvoiceService $service)
    {
        $label = $this->invoice->invoice_number ?: "Draft #{$this->invoice->id}";
        $service->deleteInvoice($this->invoice);
        session()->flash('success', "Invoice {$label} has been deleted.");

        return redirect()->route('invoices.index');
    }

    public function render()
    {
        $settings = BusinessSetting::current();

        return view('livewire.invoices.invoice-show', [
            'settings' => $settings,
        ])->layout('layouts.app', ['title' => 'Invoice '.($this->invoice->invoice_number ?: 'Draft #'.$this->invoice->id)]);
    }
}
