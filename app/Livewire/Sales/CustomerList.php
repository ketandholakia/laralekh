<?php
declare(strict_types=1);
namespace App\Livewire\Sales;

use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Services\SettlementService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Customers with their derived receivable balances for the session company.
#[Layout('layouts.app')]
final class CustomerList extends Component
{
    public string $search = '';

    public function render(): View
    {
        $settlement = app(SettlementService::class);
        $parties = Party::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('gstin', 'like', $term));
            })
            ->whereNotNull('ledger_id')->orderBy('name')->get();
        $rows = [];
        foreach ($parties as $party) {
            $balance = $settlement->ledgerBalance($party->ledger_id);
            $rows[] = ['party' => $party, 'balance' => (string) $balance, 'owed' => !$balance->isNegative()];
        }
        return view('livewire.sales.customer-list', ['customers' => $rows]);
    }
}
