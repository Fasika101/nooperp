<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Affiliate;
use Illuminate\Auth\Access\HandlesAuthorization;

class AffiliatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Affiliate');
    }

    public function view(AuthUser $authUser, Affiliate $affiliate): bool
    {
        return $authUser->can('View:Affiliate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Affiliate');
    }

    public function update(AuthUser $authUser, Affiliate $affiliate): bool
    {
        return $authUser->can('Update:Affiliate');
    }

    public function delete(AuthUser $authUser, Affiliate $affiliate): bool
    {
        return $authUser->can('Delete:Affiliate');
    }

    public function restore(AuthUser $authUser, Affiliate $affiliate): bool
    {
        return $authUser->can('Restore:Affiliate');
    }

    public function forceDelete(AuthUser $authUser, Affiliate $affiliate): bool
    {
        return $authUser->can('ForceDelete:Affiliate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Affiliate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Affiliate');
    }

    public function replicate(AuthUser $authUser, Affiliate $affiliate): bool
    {
        return $authUser->can('Replicate:Affiliate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Affiliate');
    }

}