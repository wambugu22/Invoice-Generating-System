<?php

namespace App\Livewire\Products;

use App\Models\BusinessSetting;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = null;

    public ?string $sku = null;

    public string $unit_price = '0.00';

    public string $tax_rate = '16.0000';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $settings = BusinessSetting::current();
        $this->reset(['name', 'description', 'sku', 'editingId']);
        $this->unit_price = '0.00';
        $this->tax_rate = (string) $settings->default_tax_rate;
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(Product $product): void
    {
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->sku = $product->sku;
        $this->unit_price = (string) $product->unit_price;
        $this->tax_rate = (string) $product->tax_rate;
        $this->is_active = $product->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $product = Product::findOrFail($this->editingId);
            $product->update($data);
            session()->flash('success', "Item '{$product->name}' updated.");
        } else {
            Product::create($data);
            session()->flash('success', "Item '{$this->name}' added to catalogue.");
        }

        $this->showModal = false;
        $this->reset(['name', 'description', 'sku', 'unit_price', 'tax_rate', 'is_active', 'editingId']);
    }

    public function toggleActive(Product $product): void
    {
        $product->update(['is_active' => ! $product->is_active]);
        session()->flash('success', 'Item availability updated.');
    }

    public function deleteProduct(int $id): void
    {
        $product = Product::findOrFail($id);
        $name = $product->name;
        $product->delete();
        session()->flash('success', "Item '{$name}' deleted from catalogue.");
    }

    public function clearCatalogue(): void
    {
        $count = Product::count();
        Product::query()->delete();
        session()->flash('success', "Catalogue cleared. {$count} item(s) have been removed.");
    }

    public function render()
    {
        $settings = BusinessSetting::current();

        $products = Product::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->latest()
            ->paginate(10);

        return view('livewire.products.product-index', [
            'products' => $products,
            'settings' => $settings,
        ])->layout('layouts.app', ['title' => 'Product Catalogue']);
    }
}
