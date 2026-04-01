<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CrmLeadStage;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmLeadStagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CrmLeadStage');
    }

    public function view(AuthUser $authUser, CrmLeadStage $crmLeadStage): bool
    {
        return $authUser->can('View:CrmLeadStage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CrmLeadStage');
    }

    public function update(AuthUser $authUser, CrmLeadStage $crmLeadStage): bool
    {
        return $authUser->can('Update:CrmLeadStage');
    }

    public function delete(AuthUser $authUser, CrmLeadStage $crmLeadStage): bool
    {
        return $authUser->can('Delete:CrmLeadStage');
    }

    public function restore(AuthUser $authUser, CrmLeadStage $crmLeadStage): bool
    {
        return $authUser->can('Restore:CrmLeadStage');
    }

    public function forceDelete(AuthUser $authUser, CrmLeadStage $crmLeadStage): bool
    {
        return $authUser->can('ForceDelete:CrmLeadStage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CrmLeadStage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CrmLeadStage');
    }

    public function replicate(AuthUser $authUser, CrmLeadStage $crmLeadStage): bool
    {
        return $authUser->can('Replicate:CrmLeadStage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CrmLeadStage');
    }

}