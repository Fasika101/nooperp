<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProjectPolicy
{
    /** Any authenticated user may browse the projects list (query is scoped separately). */
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    /** Any authenticated user may view a project they can see in the list. */
    public function view(AuthUser $user, Project $project): bool
    {
        return true;
    }

    /** Any authenticated user may create a project. */
    public function create(AuthUser $user): bool
    {
        return true;
    }

    /** Only the project creator or super_admin may edit settings. */
    public function update(AuthUser $user, Project $project): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $project->created_by === (int) $user->id;
    }

    /** Only the project creator or super_admin may delete a project. */
    public function delete(AuthUser $user, Project $project): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $project->created_by === (int) $user->id;
    }

    public function restore(AuthUser $user, Project $project): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(AuthUser $user, Project $project): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function restoreAny(AuthUser $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function replicate(AuthUser $user, Project $project): bool
    {
        return true;
    }

    public function reorder(AuthUser $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
