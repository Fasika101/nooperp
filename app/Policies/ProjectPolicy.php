<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Project');
    }

    public function view(AuthUser $authUser, Project $project): bool
    {
        return $authUser->can('View:Project');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Project');
    }

    public function update(AuthUser $authUser, Project $project): bool
    {
        if (! $authUser->can('Update:Project')) {
            return false;
        }

        // super_admin and manager bypass the creator restriction
        if ($authUser->hasRole(['super_admin', 'manager'])) {
            return true;
        }

        return (int) $project->created_by === (int) $authUser->id;
    }

    public function delete(AuthUser $authUser, Project $project): bool
    {
        if (! $authUser->can('Delete:Project')) {
            return false;
        }

        if ($authUser->hasRole(['super_admin', 'manager'])) {
            return true;
        }

        return (int) $project->created_by === (int) $authUser->id;
    }

    public function restore(AuthUser $authUser, Project $project): bool
    {
        return $authUser->can('Restore:Project');
    }

    public function forceDelete(AuthUser $authUser, Project $project): bool
    {
        return $authUser->can('ForceDelete:Project');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Project');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Project');
    }

    public function replicate(AuthUser $authUser, Project $project): bool
    {
        return $authUser->can('Replicate:Project');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Project');
    }

}