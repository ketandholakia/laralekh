<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
class PurchasePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['purchases.view', 'purchases.create', 'purchases.post'] as $name) Permission::findOrCreate($name, 'web');
    }
}
