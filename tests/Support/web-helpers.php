<?php

// Shared helpers for HTTP/permission feature tests. Names are distinct from the
// Step 1 domain-test helpers (companyFixture, salesFixture, inSalesCompany).

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Actions\PostInvoice;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Actions\SaveReceiptDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Shared\Models\Role;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            fn (string $name) => Permission::findOrCreate($name, 'web'),
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
    (new StateSeeder)->run();
    $company = companyFixture();

    return app(TenantContext::class)->run($company->id, fn () => $callback($company));
}

// A logged-in user whose session company and financial year are selected, with
// the seeded chart of accounts available inside the tenant scope.
function webSession(string $role = 'accountant', array $permissions = ACCOUNTANT_PERMISSIONS): array
{
    (new StateSeeder)->run();
    $user = webUser();
    $company = companyFixture();
    addMembership($user, $company);
    assignCompanyRole($user, $company, $role, $permissions);
    app(TenantContext::class)->run($company->id, fn (): mixed => (new ChartOfAccountsSeeder)->run());
    selectSessionCompany($user, $company, salesYear($company));

    return [$user, $company];
}

function inTenantContext(Company $company, Closure $callback): mixed
{
    return app(TenantContext::class)->run($company->id, $callback);
}

function selectSessionCompany(User $user, Company $company, FinancialYear $year): void
{
    app(CompanySession::class)->select($user, $company->id, $year->id);
}

function salesYear(Company $company): FinancialYear
{
    return app(TenantContext::class)->run($company->id,
        fn (): FinancialYear => FinancialYear::firstOrCreate(
            ['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'],
        ));
}

function verifiedRate(?string $code = 'GST18', string $rate = '18'): TaxRate
{
    return TaxRate::firstOrCreate(['code' => $code], [
        'rate' => $rate, 'effective_from' => '2026-04-01', 'is_verified' => true,
        'legal_reference' => 'Test fixture only',
    ]);
}

function masters(): array
{
    $ledger = Ledger::firstOrCreate(['code' => 'CUSTOMER'], [
        'name' => 'Customer', 'ledger_group_id' => LedgerGroup::where('code', 'RECEIVABLES')->sole()->id]);
    $party = Party::firstOrCreate(['ledger_id' => $ledger->id], ['name' => 'Customer', 'state_code' => '27']);
    $item = Item::firstOrCreate(['sku' => 'WIDGET'], ['name' => 'Widget', 'hsn_sac' => '8471']);
    $sales = Ledger::where('code', 'SALES')->sole();

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

function cashLedger(): Ledger
{
    return Ledger::where('code', 'CASH')->sole();
}

// Posts an invoice whose total equals the given amount exactly (exempt line, no tax).
function postInvoiceFor(Party $party, string $amount = '1000.00', ?string $date = null): Invoice
{
    $item = Item::firstOrCreate(['sku' => 'WIDGET'], ['name' => 'Widget', 'hsn_sac' => '8471']);
    $sales = Ledger::where('code', 'SALES')->sole();
    $draft = app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id, [
        'date' => $date ?? '2026-11-06',
    ]), [linePayload($item->id, $sales->id, null, ['unit_price' => $amount, 'tax_category' => 'exempt'])]);

    return app(PostInvoice::class)->handle($draft->id)->fresh(['items', 'party', 'voucher']);
}

function receiptDraft(Party $party, string $amount, array $overrides = []): Receipt
{
    return app(SaveReceiptDraft::class)->handle(null, array_merge([
        'party_id' => $party->id, 'ledger_id' => cashLedger()->id, 'date' => '2026-11-06',
        'payment_reference' => 'UTR-1', 'narration' => 'On account', 'amount' => $amount,
    ], $overrides));
}

function receiptSession(string $role = 'accountant', array $permissions = [...ACCOUNTANT_PERMISSIONS, 'receipts.view', 'receipts.create', 'receipts.post', 'receipts.allocate', 'reversals.post']): array
{
    return webSession($role, $permissions);
}

// Asserts a callable aborts with exactly HTTP 403 (theLivewire guard pattern).
function assert403(Closure $fn): void
{
    try {
        $fn();
        throw new RuntimeException('Expected a 403 abort, but the call succeeded.');
    } catch (HttpException $e) {
        if ($e->getStatusCode() !== 403) {
            throw new RuntimeException('Expected HTTP 403, got '.$e->getStatusCode().': '.$e->getMessage());
        }
    }
}
