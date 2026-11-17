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

// Balance sheet as at a date, from cumulative posted voucher movements.
// Read-only; the sheet includes retained profit so it reconciles.
#[Layout('layouts.app')]
final class BalanceSheet extends Component
{
    #[Url]
    public string $until = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }

    public function render(): View
    {
        app(CompanySession::class)->companyId();
        $report = app(FinancialReports::class)->balanceSheet(
            $this->until !== '' ? $this->until : CarbonImmutable::now()->toDateString(),
        );

        return view('livewire.accounting.balance-sheet', ['report' => $report]);
    }
}
