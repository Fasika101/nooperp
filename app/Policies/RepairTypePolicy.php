<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepairType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RepairTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RepairType');
    }

    public function view(AuthUser $authUser, RepairType $repairType): bool
    {
        return $authUser->can('View:RepairType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RepairType');
    }

    public function update(AuthUser $authUser, RepairType $repairType): bool
    {
        return $authUser->can('Update:RepairType');
    }

    public function delete(AuthUser $authUser, RepairType $repairType): bool
    {
        return $authUser->can('Delete:RepairType');
    }

    public function restore(AuthUser $authUser, RepairType $repairType): bool
    {
        return $authUser->can('Restore:RepairType');
    }

    public function forceDelete(AuthUser $authUser, RepairType $repairType): bool
    {
        return $authUser->can('ForceDelete:RepairType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RepairType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RepairType');
    }

    public function replicate(AuthUser $authUser, RepairType $repairType): bool
    {
        return $authUser->can('Replicate:RepairType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RepairType');
    }
}
