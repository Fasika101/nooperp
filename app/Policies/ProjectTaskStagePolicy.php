<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProjectTaskStage;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectTaskStagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProjectTaskStage');
    }

    public function view(AuthUser $authUser, ProjectTaskStage $projectTaskStage): bool
    {
        return $authUser->can('View:ProjectTaskStage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProjectTaskStage');
    }

    public function update(AuthUser $authUser, ProjectTaskStage $projectTaskStage): bool
    {
        return $authUser->can('Update:ProjectTaskStage');
    }

    public function delete(AuthUser $authUser, ProjectTaskStage $projectTaskStage): bool
    {
        return $authUser->can('Delete:ProjectTaskStage');
    }

    public function restore(AuthUser $authUser, ProjectTaskStage $projectTaskStage): bool
    {
        return $authUser->can('Restore:ProjectTaskStage');
    }

    public function forceDelete(AuthUser $authUser, ProjectTaskStage $projectTaskStage): bool
    {
        return $authUser->can('ForceDelete:ProjectTaskStage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProjectTaskStage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProjectTaskStage');
    }

    public function replicate(AuthUser $authUser, ProjectTaskStage $projectTaskStage): bool
    {
        return $authUser->can('Replicate:ProjectTaskStage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProjectTaskStage');
    }

}