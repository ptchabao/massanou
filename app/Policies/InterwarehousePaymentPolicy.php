<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InterwarehousePayment;
use App\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class InterwarehousePaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_payment')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_payment')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'interwarehouse_payment')->first();
        if (!$permission)
            return true;
        return $user->hasRole($permission->roles);
    }
}
