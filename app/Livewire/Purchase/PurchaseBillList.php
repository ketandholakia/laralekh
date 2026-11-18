<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
final class PurchaseBillList extends Component
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

    public function boot(): void
    {
        $company = app(CompanySession::class)->companyId();
        abort_if($company === null, 403);
        abort_unless(auth()->user()->isMemberOf($company) && auth()->user()->can('purchases.view'), 403);
    }

    public function render(): View
    {
        $bills = PurchaseBill::query()->when(trim($this->search) !== '', fn ($q) => $q->where('supplier_bill_number', 'like', '%'.trim($this->search).'%'))
            ->with('party:id,name')->orderByDesc('date')->paginate(10);

        return view('livewire.purchase.purchase-bill-list', compact('bills'));
    }
}
