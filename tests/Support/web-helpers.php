<?php
// Shared helpers for HTTP/permission feature tests. Names are distinct from the
// Step 1 domain-test helpers (companyFixture, salesFixture, inSalesCompany).

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Models\Role;
use App\Domain\Shared\Tenancy\{CompanySession, TenantContext};
use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash};

function webUser(string $email = 'worker@example.test', string $password = 'password123'): User
{
    return User::create(['name' => 'Test User', 'email' => $email, 'password' => Hash::make($password)]);
}

function addMembership(User $user, Company $company): void
{
    DB::table('company_user')->insertOrIgnore(['company_id' => $company->id, 'user_id' => $user->id]);
}

// Roles and their permission pivots are company-team scoped in this project.
function assignCompanyRole(User $user, Company $company, string $roleName, array $permissions): void
{
    app(TenantContext::class)->run($company->id, function () use ($user, $roleName, $permissions): void {
        $role = Role::create(['name' => $roleName]);
        // Spatie resolves string names but never creates them; define first.
        $role->syncPermissions(array_map(
            fn (string $name) => \Spatie\Permission\Models\Permission::findOrCreate($name, 'web'),
            $permissions,
        ));
        $user->assignRole($role);
    });
}

const ACCOUNTANT_PERMISSIONS = ['invoices.view', 'invoices.create', 'invoices.post'];
const CLERK_PERMISSIONS = ['invoices.view', 'invoices.create'];

function companyFixture(string $name = 'Company'): Company
{
    DB::table('states')->insertOrIgnore(['code' => '27', 'name' => 'Maharashtra']);
    return Company::create(['name' => $name, 'state_code' => '27']);
}

function inSalesCompany(Closure $callback): mixed
{
    (new \Database\Seeders\StateSeeder)->run();
    $company = companyFixture();
    return app(TenantContext::class)->run($company->id, fn () => $callback($company));
}

// A logged-in user whose session company and financial year are selected, with
// the seeded chart of accounts available inside the tenant scope.
function webSession(string $role = 'accountant', array $permissions = ACCOUNTANT_PERMISSIONS): array
{
    (new \Database\Seeders\StateSeeder)->run();
    $user = webUser();
    $company = companyFixture();
    addMembership($user, $company);
    assignCompanyRole($user, $company, $role, $permissions);
    app(TenantContext::class)->run($company->id, fn (): mixed => (new \Database\Seeders\ChartOfAccountsSeeder)->run());
    selectSessionCompany($user, $company, salesYear($company));
    return [$user, $company];
}

function inTenantContext(Company $company, Closure $callback): mixed
{
    return app(TenantContext::class)->run($company->id, $callback);
}

function selectSessionCompany(User $user, Company $company, \App\Domain\Masters\Models\FinancialYear $year): void
{
    app(CompanySession::class)->select($user, $company->id, $year->id);
}

function salesYear(Company $company): \App\Domain\Masters\Models\FinancialYear
{
    return app(TenantContext::class)->run($company->id,
        fn (): \App\Domain\Masters\Models\FinancialYear => \App\Domain\Masters\Models\FinancialYear::firstOrCreate(
            ['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'],
        ));
}

function verifiedRate(?string $code = 'GST18', string $rate = '18'): \App\Domain\Gst\Models\TaxRate
{
    return \App\Domain\Gst\Models\TaxRate::firstOrCreate(['code' => $code], [
        'rate' => $rate, 'effective_from' => '2026-04-01', 'is_verified' => true,
        'legal_reference' => 'Test fixture only',
    ]);
}

function masters(): array
{
    $ledger = \App\Domain\Accounting\Models\Ledger::firstOrCreate(['code' => 'CUSTOMER'], [
        'name' => 'Customer', 'ledger_group_id' => \App\Domain\Accounting\Models\LedgerGroup::where('code', 'RECEIVABLES')->sole()->id]);
    $party = \App\Domain\Masters\Models\Party::firstOrCreate(['ledger_id' => $ledger->id], ['name' => 'Customer', 'state_code' => '27']);
    $item = \App\Domain\Inventory\Models\Item::firstOrCreate(['sku' => 'WIDGET'], ['name' => 'Widget', 'hsn_sac' => '8471']);
    $sales = \App\Domain\Accounting\Models\Ledger::where('code', 'SALES')->sole();
    return [$party, $item, $sales];
}

function draftPayload(int $partyId, array $overrides = []): array
{
    return $overrides + [
        'party_id' => $partyId, 'date' => '2026-11-05', 'due_date' => null, 'series' => 'SI',
        'supply_type' => 'domestic', 'place_of_supply' => '27', 'zero_rated_mode' => 'none',
        'zero_rated_eligible' => false, 'sez_authorized' => false, 'reverse_charge' => false,
    ];
}

function linePayload(int $itemId, int $ledgerId, ?int $rateId, array $overrides = []): array
{
    return $overrides + [
        'item_id' => $itemId, 'sales_ledger_id' => $ledgerId, 'tax_rate_id' => $rateId,
        'description' => 'Widget', 'hsn_sac' => '8471', 'quantity' => '1',
        'unit_price' => '1000.00', 'discount' => '0.00', 'tax_category' => 'taxable',
    ];
}
