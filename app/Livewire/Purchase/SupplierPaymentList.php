<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Purchase\Models\SupplierPayment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Supplier-payment register for the session company. All queries run inside
// the tenant scope entered by the company.context middleware.
#[Layout('layouts.app')]
final class SupplierPaymentList extends Component
{
    use WithPagination;

    // Render register pagination with the shared LaraLekh view; Livewire's
    // WithPagination otherwise overrides the Laravel default view.
    public function paginationView(): string
    {
        return 'pagination.laralekh';
    }

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function render(): View
    {
        $payments = SupplierPayment::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)).'%';
                $query->where(fn ($q) => $q
                    ->where('number', 'like', $term)
                    ->orWhereHas('party', fn ($p) => $p->where('parties.name', 'like', $term)));
            })
            ->when(in_array($this->status, ['draft', 'posted'], true), fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('date')->orderBy('id')
            ->with('party:id,name')
            ->paginate(10);

        return view('livewire.purchase.supplier-payment-list', ['payments' => $payments]);
    }
}
