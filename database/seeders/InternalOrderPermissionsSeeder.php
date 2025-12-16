<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class InternalOrderPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            'internal_order_view',
            'internal_order_add',
            'internal_order_edit',
            'internal_order_delete',
            'internal_order_approve',
            'internal_order_ship',
            'internal_order_receive',
        ];

        $permissionIds = [];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['label' => ucfirst(str_replace('_', ' ', $permissionName))]
            );
            $permissionIds[] = $permission->id;
        }

        // Assign to Admin role (assuming ID 1)
        $role = Role::find(1);
        if ($role) {
            // Use syncWithoutDetaching to avoid removing existing permissions
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
