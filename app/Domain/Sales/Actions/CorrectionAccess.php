<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

final class CorrectionAccess
{
    public function authorize(string $permission = 'corrections.view'): void
    {
        $user = auth()->user();
        $company = app(CompanySession::class)->companyId();
        abort_unless($user && $company && $company === app(TenantContext::class)->id() && $user->isMemberOf($company), 403);
        $user->forgetCachedPermissionRelations();
        abort_unless($user->can('corrections.view') && $user->can($permission), 403);
    }

    public function year(): int
    {
        return app(CompanySession::class)->financialYearId()
            ?? throw ValidationException::withMessages(['correction' => 'Select a financial year first.']);
    }
}
