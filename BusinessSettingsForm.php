<?php

namespace App\Livewire\Settings;

use App\Models\BusinessSetting;
use Livewire\Component;

class BusinessSettingsForm extends Component
{
    public string $business_name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $address = null;

    public ?string $kra_pin = null;

    public string $currency = 'KES';

    public string $invoice_prefix = 'INV';

    public ?string $default_payment_terms = null;

    public string $default_tax_rate = '16.0000';

    public ?string $payment_instructions = null;

    public ?string $terms_and_conditions = null;

    protected function rules(): array
    {
        return [
            'business_name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'kra_pin' => 'nullable|string|max:20',
            'currency' => 'required|string|size:3',
            'invoice_prefix' => 'required|string|max:20',
            'default_payment_terms' => 'nullable|string|max:100',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'payment_instructions' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
        ];
    }

    public function mount(): void
    {
        $settings = BusinessSetting::current();

        $this->business_name = $settings->business_name;
        $this->email = $settings->email;
        $this->phone = $settings->phone;
        $this->address = $settings->address;
        $this->kra_pin = $settings->kra_pin;
        $this->currency = $settings->currency;
        $this->invoice_prefix = $settings->invoice_prefix;
        $this->default_payment_terms = $settings->default_payment_terms;
        $this->default_tax_rate = (string) $settings->default_tax_rate;
        $this->payment_instructions = $settings->payment_instructions;
        $this->terms_and_conditions = $settings->terms_and_conditions;
    }

    public function save(): void
    {
        $data = $this->validate();

        $settings = BusinessSetting::current();
        $settings->update($data);

        session()->flash('success', 'Business settings updated successfully.');
    }

    public function render()
    {
        return view('livewire.settings.business-settings-form')
            ->layout('layouts.app', ['title' => 'Business Settings']);
    }
}
