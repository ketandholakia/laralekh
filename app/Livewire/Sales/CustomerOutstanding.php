<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Services\SettlementService;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Per-customer settlement view: document outstanding balances with unapplied
// advances and excess customer credits shown separately from what is owed.
#[Layout('layouts.app')]
final class CustomerOutstanding extends Component
{
    public Party $party;

    public function mount(int|string $partyId): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('receipts.view'), 403);
        $this->party = Party::findOrFail((int) $partyId);
    }

    public function render(): View
    {
        $summary = app(SettlementService::class)->customerSummary($this->party->id);

        return view('livewire.sales.customer-outstanding', ['summary' => $summary]);
    }
}
