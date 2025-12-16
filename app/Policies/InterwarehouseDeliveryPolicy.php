<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InterwarehouseDelivery;
use App\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class InterwarehouseDeliveryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_delivery')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_delivery')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_delivery')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }
}
