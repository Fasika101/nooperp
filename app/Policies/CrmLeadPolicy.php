<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CrmLead;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmLeadPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CrmLead');
    }

    public function view(AuthUser $authUser, CrmLead $crmLead): bool
    {
        return $authUser->can('View:CrmLead');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CrmLead');
    }

    public function update(AuthUser $authUser, CrmLead $crmLead): bool
    {
        return $authUser->can('Update:CrmLead');
    }

    public function delete(AuthUser $authUser, CrmLead $crmLead): bool
    {
        return $authUser->can('Delete:CrmLead');
    }

    public function restore(AuthUser $authUser, CrmLead $crmLead): bool
    {
        return $authUser->can('Restore:CrmLead');
    }

    public function forceDelete(AuthUser $authUser, CrmLead $crmLead): bool
    {
        return $authUser->can('ForceDelete:CrmLead');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CrmLead');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CrmLead');
    }

    public function replicate(AuthUser $authUser, CrmLead $crmLead): bool
    {
        return $authUser->can('Replicate:CrmLead');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CrmLead');
    }

}