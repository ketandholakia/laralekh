<?php
declare(strict_types=1);
namespace App\Livewire\Purchase;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\{Layout, Locked};
use Livewire\Component;
#[Layout('layouts.app')]
final class PurchaseBillView extends Component
{
    #[Locked] public int $billId;
    public function boot(): void
    {
        $company = app(CompanySession::class)->companyId();
        abort_if($company === null, 403);
        abort_unless(auth()->user()->isMemberOf($company) && auth()->user()->can('purchases.view'), 403);
    }
    public function mount(int|string $bill): void { $this->billId = (int) $bill; }
    public function render(): View { return view('livewire.purchase.purchase-bill-view', ['bill' => PurchaseBill::with(['items','party','voucher'])->findOrFail($this->billId)]); }
}
