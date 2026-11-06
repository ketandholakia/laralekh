<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class CorrectionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['corrections.view', 'corrections.create', 'corrections.post', 'reversals.post'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
