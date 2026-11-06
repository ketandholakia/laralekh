<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Services\CustomerStatement as StatementService;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Interactive as-of customer statement; the PDF renders the same data.
#[Layout('layouts.app')]
final class CustomerStatement extends Component
{
    public Party $party;

    public string $from = '';

    public string $asOf = '';

    public function mount(int|string $partyId): void
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403, 'Select a company first.');
        abort_unless($user->isMemberOf($companyId), 403);
        abort_unless($user->can('receipts.view'), 403);
        $this->party = Party::findOrFail((int) $partyId);
        $this->asOf = now()->toDateString();
    }

    public function render(): View
    {
        $statement = null;
        try {
            $statement = $this->from !== '' && $this->asOf !== ''
                ? app(StatementService::class)->build($this->party->id, $this->from, $this->asOf)
                : null;
        } catch (\DomainException $e) {
            $this->addError('asOf', $e->getMessage());
        }

        return view('livewire.sales.customer-statement', ['statement' => $statement]);
    }
}
