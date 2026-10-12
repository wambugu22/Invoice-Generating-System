<?php

namespace App\Livewire;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $settings = BusinessSetting::current();

        $totalInvoiced = (float) Invoice::where('status', '!=', 'void')->sum('grand_total');
        $totalCollected = (float) Payment::sum('amount');
        $outstandingBalance = max(0, $totalInvoiced - $totalCollected);

        $overdueInvoices = Invoice::where('status', '!=', 'void')
            ->where('status', '!=', 'draft')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->with('payments')
            ->get()
            ->filter(fn ($inv) => (float) $inv->balance_due > 0);

        $overdueAmount = $overdueInvoices->sum(fn ($inv) => (float) $inv->balance_due);
        $overdueCount = $overdueInvoices->count();

        $recentInvoices = Invoice::with(['customer', 'payments'])
            ->latest('issue_date')
            ->latest('id')
            ->take(6)
            ->get();

        $recentPayments = Payment::with('invoice')
            ->latest('payment_date')
            ->latest('id')
            ->take(5)
            ->get();

        return view('livewire.dashboard', [
            'settings' => $settings,
            'totalInvoiced' => $totalInvoiced,
            'totalCollected' => $totalCollected,
            'outstandingBalance' => $outstandingBalance,
            'overdueAmount' => $overdueAmount,
            'overdueCount' => $overdueCount,
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
