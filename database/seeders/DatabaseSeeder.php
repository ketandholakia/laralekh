<?php

namespace Database\Seeders;

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Tenancy\TenantContext;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(StateSeeder::class);
        $this->call(CorrectionPermissionSeeder::class);
        $this->call(FinancialYearPermissionSeeder::class);
        $this->call(ReportingPermissionSeeder::class);
        $this->call(PurchasePermissionSeeder::class);
        $this->call(GstReturnsPermissionSeeder::class);
        $this->call(UserPermissionSeeder::class);
        Company::each(function (Company $company): void {
            app(TenantContext::class)->run($company->id, function (): void {
                $this->call([ChartOfAccountsSeeder::class, GstSlabSeeder::class]);
            });
        });
    }
}
