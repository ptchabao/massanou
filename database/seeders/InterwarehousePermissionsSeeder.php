<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InterwarehousePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            'interwarehouse_request_view',
            'interwarehouse_request_add',
            'interwarehouse_request_edit',
            'interwarehouse_request_delete',
            'interwarehouse_payment_view',
            'interwarehouse_payment_add',
            'interwarehouse_payment_delete',
            'interwarehouse_delivery_view',
            'interwarehouse_delivery_add',
            'interwarehouse_delivery_edit',
        ];

        $permissionIds = [];

        foreach ($permissions as $permissionName) {
            $permission = \App\Models\Permission::firstOrCreate(
                ['name' => $permissionName],
                ['label' => ucfirst(str_replace('_', ' ', $permissionName))]
            );
            $permissionIds[] = $permission->id;
        }

        // Assign to Admin role (assuming ID 1)
        $role = \App\Models\Role::find(1);
        if ($role) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
