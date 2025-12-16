<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InternalOrder;
use App\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class InternalOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function view(User $user)
    {
        $permission = Permission::where('name', 'internal_order_view')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        // Allow all authenticated users to access the create form
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function update(User $user)
    {
        $permission = Permission::where('name', 'internal_order_edit')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function delete(User $user)
    {
        $permission = Permission::where('name', 'internal_order_delete')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can approve/reject internal orders.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function approve(User $user)
    {
        $permission = Permission::where('name', 'internal_order_approve')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can ship internal orders.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function ship(User $user)
    {
        $permission = Permission::where('name', 'internal_order_ship')->first();
        return $user->hasRole($permission->roles);
    }

    /**
     * Determine whether the user can receive internal orders.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function receive(User $user)
    {
        $permission = Permission::where('name', 'internal_order_receive')->first();
        return $user->hasRole($permission->roles);
    }

    public function check_record(User $user, $internalOrder)
    {
        return $user->id === $internalOrder->user_id;
    }

}
