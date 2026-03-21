<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignAllPermissionsToAdminSeeder extends Seeder
{
    public function run()
    {
        $adminRole = Role::findOrCreate('admin', 'web');
        $permissions = Permission::all();
        $adminRole->syncPermissions($permissions);
    }
}
