<?php

declare(strict_types=1);

namespace App\Livewire\Purchase;

use App\Domain\Purchase\Actions\PostPurchaseDebitNote;
use App\Domain\Purchase\Models\PurchaseDebitNote;
use App\Domain\Shared\Tenancy\CompanySession;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
final class PurchaseDebitNoteView extends Component
{
    #[Locked]
    public int $debitNoteId;

    public function boot(): void
    {
        $company = app(CompanySession::class)->companyId();
        abort_if($company === null, 403);
        abort_unless(auth()->user()->isMemberOf($company) && auth()->user()->can('purchases.view'), 403);
    }

    public function mount(int|string $debitNote): void
    {
        $this->debitNoteId = (int) $debitNote;
    }

    public function post(): void
    {
        abort_unless(auth()->user()->can('purchases.post'), 403);

        try {
            $posted = app(PostPurchaseDebitNote::class)->handle($this->debitNoteId);
            session()->flash('status', 'Purchase debit note PDN/'.$posted->number.' posted successfully.');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(): View
    {
        $debitNote = PurchaseDebitNote::with(['lines.billItem', 'bill.party', 'voucher.lines.ledger'])->findOrFail($this->debitNoteId);

        return view('livewire.purchase.purchase-debit-note-view', [
            'debitNote' => $debitNote,
        ]);
    }
}
