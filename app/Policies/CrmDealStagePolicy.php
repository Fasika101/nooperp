<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CrmDealStage;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmDealStagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CrmDealStage');
    }

    public function view(AuthUser $authUser, CrmDealStage $crmDealStage): bool
    {
        return $authUser->can('View:CrmDealStage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CrmDealStage');
    }

    public function update(AuthUser $authUser, CrmDealStage $crmDealStage): bool
    {
        return $authUser->can('Update:CrmDealStage');
    }

    public function delete(AuthUser $authUser, CrmDealStage $crmDealStage): bool
    {
        return $authUser->can('Delete:CrmDealStage');
    }

    public function restore(AuthUser $authUser, CrmDealStage $crmDealStage): bool
    {
        return $authUser->can('Restore:CrmDealStage');
    }

    public function forceDelete(AuthUser $authUser, CrmDealStage $crmDealStage): bool
    {
        return $authUser->can('ForceDelete:CrmDealStage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CrmDealStage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CrmDealStage');
    }

    public function replicate(AuthUser $authUser, CrmDealStage $crmDealStage): bool
    {
        return $authUser->can('Replicate:CrmDealStage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CrmDealStage');
    }

}