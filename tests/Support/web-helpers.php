<?php
// Shared helpers for HTTP/permission feature tests. Names are distinct from the
// Step 1 domain-test helpers (companyFixture, salesFixture, inSalesCompany).

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Models\Role;
use App\Domain\Shared\Tenancy\TenantContext;
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
