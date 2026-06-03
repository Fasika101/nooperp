<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderDeletionLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OrderDeletionLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrderDeletionLog');
    }

    public function view(AuthUser $authUser, OrderDeletionLog $orderDeletionLog): bool
    {
        return $authUser->can('View:OrderDeletionLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrderDeletionLog');
    }

    public function update(AuthUser $authUser, OrderDeletionLog $orderDeletionLog): bool
    {
        return $authUser->can('Update:OrderDeletionLog');
    }

    public function delete(AuthUser $authUser, OrderDeletionLog $orderDeletionLog): bool
    {
        return $authUser->can('Delete:OrderDeletionLog');
    }

    public function restore(AuthUser $authUser, OrderDeletionLog $orderDeletionLog): bool
    {
        return $authUser->can('Restore:OrderDeletionLog');
    }

    public function forceDelete(AuthUser $authUser, OrderDeletionLog $orderDeletionLog): bool
    {
        return $authUser->can('ForceDelete:OrderDeletionLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrderDeletionLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrderDeletionLog');
    }

    public function replicate(AuthUser $authUser, OrderDeletionLog $orderDeletionLog): bool
    {
        return $authUser->can('Replicate:OrderDeletionLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrderDeletionLog');
    }
}
