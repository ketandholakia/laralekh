<?php
declare(strict_types=1);
namespace App\Livewire\Sales;

use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Receipt register for the session company with customer/date/status filters.
#[Layout('layouts.app')]
final class ReceiptList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';

    public function render(): View
    {
        $receipts = Receipt::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)).'%';
                $query->where(fn ($q) => $q
                    ->where('number', 'like', $term)
                    ->orWhere('series', 'like', $term)
                    ->orWhere('payment_reference', 'like', $term)
                    ->orWhereHas('party', fn ($p) => $p->where('parties.name', 'like', $term)));
            })
            ->when(in_array($this->status, ['draft', 'posted'], true), fn ($q) => $q->where('status', $this->status))
            ->when($this->from !== '', fn ($q) => $q->where('date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->where('date', '<=', $this->to))
            ->orderByDesc('date')->orderBy('id')
            ->with('party:id,name')
            ->paginate(10);
        return view('livewire.sales.receipt-list', ['receipts' => $receipts]);
    }
}
