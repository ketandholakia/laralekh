<?php

declare(strict_types=1);

namespace App\Livewire\Accounting;

use App\Domain\Accounting\Services\FinancialReports;
use App\Domain\Shared\Tenancy\CompanySession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

// Trial balance from posted voucher movements. Read-only: it never posts,
// locks or mutates; totals reconcile because posting asserts balanced entries.
#[Layout('layouts.app')]
final class TrialBalance extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function render(): View
    {
        app(CompanySession::class)->companyId();
        $report = app(FinancialReports::class)->trialBalance(
            $this->from !== '' ? $this->from : null,
            $this->until !== '' ? $this->until : (CarbonImmutable::now()->toDateString()),
        );

        return view('livewire.accounting.trial-balance', ['report' => $report]);
    }
}
