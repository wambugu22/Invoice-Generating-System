<?php

namespace App\Livewire\Invoices;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Livewire\Component;

class InvoiceForm extends Component
{
    public ?Invoice $invoice = null;

    public bool $isEditing = false;

    // Invoice Meta
    public ?int $customer_id = null;

    public string $customer_name = '';

    public ?string $customer_email = null;

    public ?string $customer_phone = null;

    public ?string $customer_billing_address = null;

    public ?string $customer_kra_pin = null;

    public string $issue_date = '';

    public ?string $due_date = null;

    public string $currency = 'KES';

    public ?string $notes = null;

    public ?string $terms = null;

    // Quick customer creation modal state
    public bool $showQuickCustomerModal = false;

    public string $quick_name = '';

    public ?string $quick_email = null;

    public ?string $quick_phone = null;

    public ?string $quick_kra_pin = null;

    public ?string $quick_billing_address = null;

    // Line items
    public array $items = [];

    // Computed totals for reactive display
    public float $subtotal = 0.00;

    public float $discount_total = 0.00;

    public float $tax_total = 0.00;

    public float $grand_total = 0.00;

    protected $rules = [
        'customer_id' => 'required|exists:customers,id',
        'issue_date' => 'required|date',
        'due_date' => 'nullable|date|after_or_equal:issue_date',
        'currency' => 'required|string|size:3',
        'items' => 'required|array|min:1',
        'items.*.description' => 'required|string|max:1000',
        'items.*.quantity' => 'required|numeric|min:0.001',
        'items.*.unit_price' => 'required|numeric|min:0',
        'items.*.discount_amount' => 'nullable|numeric|min:0',
        'items.*.tax_rate' => 'nullable|numeric|min:0',
    ];

    public function mount(?Invoice $invoice = null)
    {
        $settings = BusinessSetting::current();

        if ($invoice && $invoice->exists) {
            $this->invoice = $invoice;
            $this->isEditing = true;

            $this->customer_id = $invoice->customer_id;
            $this->customer_name = $invoice->customer_name;
            $this->customer_email = $invoice->customer_email;
            $this->customer_phone = $invoice->customer_phone;
            $this->customer_billing_address = $invoice->customer_billing_address;
            $this->customer_kra_pin = $invoice->customer_kra_pin;

            $this->issue_date = $invoice->issue_date->format('Y-m-d');
            $this->due_date = $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null;
            $this->currency = $invoice->currency;
            $this->notes = $invoice->notes;
            $this->terms = $invoice->terms;

            $this->items = $invoice->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => (float) $item->discount_amount,
                    'tax_rate' => (float) $item->tax_rate,
                    'line_total' => (float) $item->line_total,
                ];
            })->toArray();
        } else {
            $this->issue_date = Carbon::today()->format('Y-m-d');
            $this->due_date = Carbon::today()->addDays(14)->format('Y-m-d');
            $this->currency = $settings->currency ?: 'KES';
            $this->terms = $settings->terms_and_conditions;

            // Default empty line item
            $this->items = [
                [
                    'product_id' => null,
                    'description' => '',
                    'quantity' => 1,
                    'unit_price' => 0.00,
                    'discount_amount' => 0.00,
                    'tax_rate' => (float) $settings->default_tax_rate,
                    'line_total' => 0.00,
                ],
            ];
        }

        $this->recalculate();
    }

    public function updatedCustomerId($value)
    {
        if ($value && ($customer = Customer::find($value))) {
            $this->customer_name = $customer->name;
            $this->customer_email = $customer->email;
            $this->customer_phone = $customer->phone;
            $this->customer_billing_address = $customer->billing_address;
            $this->customer_kra_pin = $customer->kra_pin;
        }
    }

    public function openQuickCustomerModal(): void
    {
        $this->reset(['quick_name', 'quick_email', 'quick_phone', 'quick_kra_pin', 'quick_billing_address']);
        $this->showQuickCustomerModal = true;
    }

    public function closeQuickCustomerModal(): void
    {
        $this->showQuickCustomerModal = false;
    }

    public function saveQuickCustomer(): void
    {
        $this->validate([
            'quick_name' => 'required|string|max:150',
            'quick_email' => 'nullable|email|max:150',
            'quick_phone' => 'nullable|string|max:30',
            'quick_kra_pin' => 'nullable|string|max:20',
            'quick_billing_address' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'name' => $this->quick_name,
            'email' => $this->quick_email,
            'phone' => $this->quick_phone,
            'kra_pin' => $this->quick_kra_pin,
            'billing_address' => $this->quick_billing_address,
            'is_active' => true,
        ]);

        $this->customer_id = $customer->id;
        $this->customer_name = $customer->name;
        $this->customer_email = $customer->email;
        $this->customer_phone = $customer->phone;
        $this->customer_kra_pin = $customer->kra_pin;
        $this->customer_billing_address = $customer->billing_address;

        $this->showQuickCustomerModal = false;
        session()->flash('success', "Customer '{$customer->name}' created and selected.");
    }

    public function addItem()
    {
        $settings = BusinessSetting::current();
        $this->items[] = [
            'product_id' => null,
            'description' => '',
            'quantity' => 1,
            'unit_price' => 0.00,
            'discount_amount' => 0.00,
            'tax_rate' => (float) $settings->default_tax_rate,
            'line_total' => 0.00,
        ];
        $this->recalculate();
    }

    public function removeItem(int $index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            $this->recalculate();
        }
    }

    public function selectProduct(int $index, ?int $productId)
    {
        if ($productId && ($product = Product::find($productId))) {
            $this->items[$index]['product_id'] = $product->id;
            $this->items[$index]['description'] = $product->name.($product->description ? " - {$product->description}" : '');
            $this->items[$index]['unit_price'] = (float) $product->unit_price;
            $this->items[$index]['tax_rate'] = (float) $product->tax_rate;
            $this->recalculate();
        }
    }

    public function updated($propertyName)
    {
        if (str_starts_with($propertyName, 'items.')) {
            $this->recalculate();
        }
    }

    public function recalculate()
    {
        $calcService = app(InvoiceService::class);
        $sub = 0.00;
        $disc = 0.00;
        $tx = 0.00;
        $grand = 0.00;

        foreach ($this->items as $i => $item) {
            $line = $calcService->calculateLineItem(
                $item['quantity'] ?? 0,
                $item['unit_price'] ?? 0,
                $item['discount_amount'] ?? 0,
                $item['tax_rate'] ?? 0
            );

            $this->items[$i]['line_total'] = $line['total'];
            $sub += $line['subtotal'];
            $disc += $line['discount'];
            $tx += $line['tax'];
            $grand += $line['total'];
        }

        $this->subtotal = round($sub, 2);
        $this->discount_total = round($disc, 2);
        $this->tax_total = round($tx, 2);
        $this->grand_total = round($grand, 2);
    }

    public function save(bool $finalize = false, ?InvoiceService $service = null)
    {
        $this->validate();
        $this->recalculate();

        $service = $service ?? app(InvoiceService::class);
        $customer = Customer::findOrFail($this->customer_id);

        if ($this->isEditing && $this->invoice) {
            $invoice = $this->invoice;
            $invoice->update([
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name ?: $customer->name,
                'customer_email' => $this->customer_email,
                'customer_phone' => $this->customer_phone,
                'customer_billing_address' => $this->customer_billing_address,
                'customer_kra_pin' => $this->customer_kra_pin,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'currency' => $this->currency,
                'notes' => $this->notes,
                'terms' => $this->terms,
            ]);

            // Recreate items
            $invoice->items()->delete();
        } else {
            $invoice = Invoice::create([
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name ?: $customer->name,
                'customer_email' => $this->customer_email,
                'customer_phone' => $this->customer_phone,
                'customer_billing_address' => $this->customer_billing_address,
                'customer_kra_pin' => $this->customer_kra_pin,
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'status' => 'draft',
                'currency' => $this->currency,
                'notes' => $this->notes,
                'terms' => $this->terms,
            ]);
        }

        foreach ($this->items as $item) {
            $line = $service->calculateLineItem(
                $item['quantity'],
                $item['unit_price'],
                $item['discount_amount'] ?? 0,
                $item['tax_rate'] ?? 0
            );

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'tax_amount' => $line['tax'],
                'line_total' => $line['total'],
            ]);
        }

        $service->syncInvoiceTotals($invoice);

        if ($finalize) {
            $invoice = $service->finalizeInvoice($invoice);
            session()->flash('success', "Invoice finalized successfully as {$invoice->invoice_number}!");
        } else {
            session()->flash('success', 'Invoice draft saved successfully.');
        }

        return redirect()->route('invoices.show', $invoice);
    }

    public function render()
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('livewire.invoices.invoice-form', [
            'customers' => $customers,
            'products' => $products,
        ])->layout('layouts.app', ['title' => $this->isEditing ? 'Edit Invoice' : 'Create Invoice']);
    }
}
