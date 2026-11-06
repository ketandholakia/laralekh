<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
class GstReturnsPermissionSeeder extends Seeder
{
    public function run():void { foreach(['returns.view','returns.export','returns.finalize'] as $permission)Permission::findOrCreate($permission,'web'); }
}
