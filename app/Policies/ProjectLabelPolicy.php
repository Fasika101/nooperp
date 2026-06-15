<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProjectLabel;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectLabelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProjectLabel');
    }

    public function view(AuthUser $authUser, ProjectLabel $projectLabel): bool
    {
        return $authUser->can('View:ProjectLabel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProjectLabel');
    }

    public function update(AuthUser $authUser, ProjectLabel $projectLabel): bool
    {
        return $authUser->can('Update:ProjectLabel');
    }

    public function delete(AuthUser $authUser, ProjectLabel $projectLabel): bool
    {
        return $authUser->can('Delete:ProjectLabel');
    }

    public function restore(AuthUser $authUser, ProjectLabel $projectLabel): bool
    {
        return $authUser->can('Restore:ProjectLabel');
    }

    public function forceDelete(AuthUser $authUser, ProjectLabel $projectLabel): bool
    {
        return $authUser->can('ForceDelete:ProjectLabel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProjectLabel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProjectLabel');
    }

    public function replicate(AuthUser $authUser, ProjectLabel $projectLabel): bool
    {
        return $authUser->can('Replicate:ProjectLabel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProjectLabel');
    }

}