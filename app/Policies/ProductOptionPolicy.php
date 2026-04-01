<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductOption;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductOptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProductOption');
    }

    public function view(AuthUser $authUser, ProductOption $productOption): bool
    {
        return $authUser->can('View:ProductOption');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProductOption');
    }

    public function update(AuthUser $authUser, ProductOption $productOption): bool
    {
        return $authUser->can('Update:ProductOption');
    }

    public function delete(AuthUser $authUser, ProductOption $productOption): bool
    {
        return $authUser->can('Delete:ProductOption');
    }

    public function restore(AuthUser $authUser, ProductOption $productOption): bool
    {
        return $authUser->can('Restore:ProductOption');
    }

    public function forceDelete(AuthUser $authUser, ProductOption $productOption): bool
    {
        return $authUser->can('ForceDelete:ProductOption');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProductOption');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProductOption');
    }

    public function replicate(AuthUser $authUser, ProductOption $productOption): bool
    {
        return $authUser->can('Replicate:ProductOption');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProductOption');
    }
}
