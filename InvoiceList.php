<?php

namespace App\Livewire\Invoices;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function duplicate(int $invoiceId, InvoiceService $service)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $newDraft = $service->duplicateInvoice($invoice);

        session()->flash('success', "Invoice duplicated into new Draft #{$newDraft->id}.");

        return redirect()->route('invoices.edit', $newDraft);
    }

    public function finalize(int $invoiceId, InvoiceService $service): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $finalized = $service->finalizeInvoice($invoice);

        session()->flash('success', "Invoice finalized as {$finalized->invoice_number}.");
    }

    public function voidInvoice(int $invoiceId, InvoiceService $service): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $service->voidInvoice($invoice, 'Voided from invoice list');

        session()->flash('success', "Invoice {$invoice->invoice_number} marked as void.");
    }

    public function deleteInvoice(int $invoiceId, InvoiceService $service): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $label = $invoice->invoice_number ?: "Draft #{$invoice->id}";
        $service->deleteInvoice($invoice);

        session()->flash('success', "Invoice {$label} deleted successfully.");
    }

    public function deleteDraft(int $invoiceId, InvoiceService $service): void
    {
        $this->deleteInvoice($invoiceId, $service);
    }

    public function render()
    {
        $settings = BusinessSetting::current();

        $query = Invoice::query()->with(['customer', 'payments']);

        if (! empty($this->search)) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term);
            });
        }

        if ($this->statusFilter !== 'all') {
            if ($this->statusFilter === 'overdue') {
                $query->where('status', '!=', 'void')
                    ->where('status', '!=', 'draft')
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', Carbon::today());
            } else {
                $query->where('status', $this->statusFilter);
            }
        }

        $invoices = $query->latest('issue_date')->latest('id')->paginate(12);

        return view('livewire.invoices.invoice-list', [
            'invoices' => $invoices,
            'settings' => $settings,
        ])->layout('layouts.app', ['title' => 'Invoices']);
    }
}
