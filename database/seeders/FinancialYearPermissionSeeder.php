<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

// Defines the financial-year administration permission. Grant it through
// company-scoped roles as appropriate.
class FinancialYearPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate('years.manage', 'web');
    }
}
