<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Company/financial-year selection. Only membership-backed ids reach the
// session; the company id is never taken from any other browser input.
final class CompanySelectionController extends Controller
{
    public function index(Request $request)
    {
        $session = app(CompanySession::class);
        $companies = $request->user()->companies()->orderBy('name')->get(['companies.id', 'companies.name']);
        $selectedId = $session->companyId();
        $years = [];
        if ($selectedId !== null && $request->user()->isMemberOf($selectedId)) {
            // Read inside the selected company's scope so only its years appear.
            $years = app(TenantContext::class)
                ->run($selectedId, fn (): array => FinancialYear::where('is_closed', false)
                    ->orderByDesc('starts_on')->get(['id', 'name', 'starts_on', 'ends_on'])->all());
        }

        return view('company.select', [
            'companies' => $companies, 'selectedCompanyId' => $selectedId,
            'selectedYearId' => $session->financialYearId(), 'years' => $years,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'min:1'],
            'financial_year_id' => ['nullable', 'integer', 'min:1'],
        ]);
        app(CompanySession::class)->select(
            $request->user(), (int) $data['company_id'],
            isset($data['financial_year_id']) ? (int) $data['financial_year_id'] : null,
        );

        return redirect()->intended('/invoices');
    }
}
