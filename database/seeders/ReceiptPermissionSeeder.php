<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

// Defines receipt/statement permissions without widening any existing role.
// Grant them through the existing company-scoped roles as appropriate.
class ReceiptPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['receipts.view', 'receipts.create', 'receipts.post', 'receipts.allocate'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
