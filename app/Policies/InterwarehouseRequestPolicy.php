<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InterwarehouseRequest;
use App\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class InterwarehouseRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_view')->first();
        if (!$permission)
            return true; // Default allow if permission not set
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_add')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_edit')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_delete')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create/manage proforma.
     */
    public function proforma(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_proforma')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }
}
