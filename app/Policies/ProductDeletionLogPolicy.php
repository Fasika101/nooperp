<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductDeletionLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductDeletionLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProductDeletionLog');
    }

    public function view(AuthUser $authUser, ProductDeletionLog $productDeletionLog): bool
    {
        return $authUser->can('View:ProductDeletionLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProductDeletionLog');
    }

    public function update(AuthUser $authUser, ProductDeletionLog $productDeletionLog): bool
    {
        return $authUser->can('Update:ProductDeletionLog');
    }

    public function delete(AuthUser $authUser, ProductDeletionLog $productDeletionLog): bool
    {
        return $authUser->can('Delete:ProductDeletionLog');
    }

    public function restore(AuthUser $authUser, ProductDeletionLog $productDeletionLog): bool
    {
        return $authUser->can('Restore:ProductDeletionLog');
    }

    public function forceDelete(AuthUser $authUser, ProductDeletionLog $productDeletionLog): bool
    {
        return $authUser->can('ForceDelete:ProductDeletionLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProductDeletionLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProductDeletionLog');
    }

    public function replicate(AuthUser $authUser, ProductDeletionLog $productDeletionLog): bool
    {
        return $authUser->can('Replicate:ProductDeletionLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProductDeletionLog');
    }
}
