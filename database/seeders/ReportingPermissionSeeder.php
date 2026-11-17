<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

// Defines the financial-report permission. Grant it through company-scoped
// roles as appropriate.
class ReportingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate('reports.view', 'web');
    }
}
