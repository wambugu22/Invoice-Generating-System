<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $billing_address = null;

    public ?string $kra_pin = null;

    public ?string $notes = null;

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'billing_address' => 'nullable|string',
            'kra_pin' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'phone', 'billing_address', 'kra_pin', 'notes', 'is_active', 'editingId']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(Customer $customer): void
    {
        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->email = $customer->email;
        $this->phone = $customer->phone;
        $this->billing_address = $customer->billing_address;
        $this->kra_pin = $customer->kra_pin;
        $this->notes = $customer->notes;
        $this->is_active = $customer->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $customer = Customer::findOrFail($this->editingId);
            $customer->update($data);
            session()->flash('success', "Customer '{$customer->name}' updated.");
        } else {
            Customer::create($data);
            session()->flash('success', "Customer '{$this->name}' registered successfully.");
        }

        $this->showModal = false;
        $this->reset(['name', 'email', 'phone', 'billing_address', 'kra_pin', 'notes', 'is_active', 'editingId']);
    }

    public function toggleActive(Customer $customer): void
    {
        $customer->update(['is_active' => ! $customer->is_active]);
        session()->flash('success', 'Customer status updated.');
    }

    public function deleteCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $name = $customer->name;
        $customer->delete();
        session()->flash('success', "Customer '{$name}' has been deleted.");
    }

    public function render()
    {
        $customers = Customer::query()
            ->withCount('invoices')
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('kra_pin', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            })
            ->latest()
            ->paginate(10);

        return view('livewire.customers.customer-index', [
            'customers' => $customers,
        ])->layout('layouts.app', ['title' => 'Customers']);
    }
}
