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

// Profit & loss for a period, from posted voucher movements. Read-only.
#[Layout('layouts.app')]
final class ProfitAndLoss extends Component
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
        $until = $this->until !== '' ? $this->until : CarbonImmutable::now()->toDateString();
        $from = $this->from !== '' ? $this->from : CarbonImmutable::parse($until)->startOfYear()->toDateString();
        $report = app(FinancialReports::class)->profitAndLoss($from, $until);
        $report['from'] = $from;
        $report['until'] = $until;

        return view('livewire.accounting.profit-and-loss', ['report' => $report]);
    }
}
