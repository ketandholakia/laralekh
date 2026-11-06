<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

// Enters the authorized tenant scope for the whole request. The session company
// is revalidated against live membership on every request, so demoting a member
// cuts access immediately even with a valid session. The financial year in the
// session is resolved inside the company scope, so a stale or foreign year 404s.
final class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $session = app(CompanySession::class);
        $companyId = $session->companyId();
        if ($companyId === null || ! $user->isMemberOf($companyId)) {
            $session->forget();

            return redirect()->route('company.select');
        }

        // TenantContext::run also points Spatie's permission team at this
        // company for the duration of the request, and restores both after it.
        return app(TenantContext::class)->run($companyId, function () use ($request, $next, $session): mixed {
            $yearId = $session->financialYearId();
            if ($yearId !== null) {
                FinancialYear::whereKey($yearId)->sole();
            }

            return $next($request);
        });
    }
}
