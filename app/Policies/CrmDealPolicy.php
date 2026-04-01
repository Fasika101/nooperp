<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CrmDeal;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmDealPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CrmDeal');
    }

    public function view(AuthUser $authUser, CrmDeal $crmDeal): bool
    {
        return $authUser->can('View:CrmDeal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CrmDeal');
    }

    public function update(AuthUser $authUser, CrmDeal $crmDeal): bool
    {
        return $authUser->can('Update:CrmDeal');
    }

    public function delete(AuthUser $authUser, CrmDeal $crmDeal): bool
    {
        return $authUser->can('Delete:CrmDeal');
    }

    public function restore(AuthUser $authUser, CrmDeal $crmDeal): bool
    {
        return $authUser->can('Restore:CrmDeal');
    }

    public function forceDelete(AuthUser $authUser, CrmDeal $crmDeal): bool
    {
        return $authUser->can('ForceDelete:CrmDeal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CrmDeal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CrmDeal');
    }

    public function replicate(AuthUser $authUser, CrmDeal $crmDeal): bool
    {
        return $authUser->can('Replicate:CrmDeal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CrmDeal');
    }

}