<?php
declare(strict_types=1);
namespace App\Domain\Shared\Tenancy;

use App\Domain\Masters\Models\FinancialYear;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;

// Session-backed selection of the company and financial year a request works in.
// The company id stored here is the ONLY company id trusted by HTTP code; it is
// revalidated against live membership on every request and never accepted from
// browser input. Selecting a company always requires membership, and switching
// clears the Spatie permission relations cached on the loaded user.
final class CompanySession
{
    private const COMPANY_KEY = 'company_id';
    private const YEAR_KEY = 'financial_year_id';

    public function __construct(private readonly Store $session) {}

    public function companyId(): ?int
    {
        $id = $this->session->get(self::COMPANY_KEY);
        return is_int($id) && $id > 0 ? $id : null;
    }

    public function financialYearId(): ?int
    {
        $id = $this->session->get(self::YEAR_KEY);
        return is_int($id) && $id > 0 ? $id : null;
    }

    public function select(User $user, int $companyId, ?int $financialYearId = null): void
    {
        if (!$user->isMemberOf($companyId)) {
            throw ValidationException::withMessages(['company_id' => 'You are not a member of that company.']);
        }
        $switching = $this->companyId() !== $companyId;
        // The financial year must belong to the company being selected; validate
        // it inside that company's tenant scope. Anything else in the session
        // belongs to the previous company and is discarded on switch.
        if ($financialYearId !== null) {
            try {
                app(TenantContext::class)->run($companyId, function () use ($financialYearId): void {
                    FinancialYear::whereKey($financialYearId)->sole();
                });
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'financial_year_id' => 'That financial year does not belong to the selected company.',
                ]);
            }
            $this->session->put(self::YEAR_KEY, $financialYearId);
        } elseif ($switching) {
            $this->session->forget(self::YEAR_KEY);
        }
        if ($switching) {
            $user->forgetCachedPermissionRelations();
        }
        $this->session->put(self::COMPANY_KEY, $companyId);
    }

    public function forget(): void
    {
        $this->session->forget(self::COMPANY_KEY);
        $this->session->forget(self::YEAR_KEY);
    }
}
