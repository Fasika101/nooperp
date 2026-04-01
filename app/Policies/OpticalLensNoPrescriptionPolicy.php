<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OpticalLensNoPrescription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OpticalLensNoPrescriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OpticalLensNoPrescription');
    }

    public function view(AuthUser $authUser, OpticalLensNoPrescription $opticalLensNoPrescription): bool
    {
        return $authUser->can('View:OpticalLensNoPrescription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OpticalLensNoPrescription');
    }

    public function update(AuthUser $authUser, OpticalLensNoPrescription $opticalLensNoPrescription): bool
    {
        return $authUser->can('Update:OpticalLensNoPrescription');
    }

    public function delete(AuthUser $authUser, OpticalLensNoPrescription $opticalLensNoPrescription): bool
    {
        return $authUser->can('Delete:OpticalLensNoPrescription');
    }

    public function restore(AuthUser $authUser, OpticalLensNoPrescription $opticalLensNoPrescription): bool
    {
        return $authUser->can('Restore:OpticalLensNoPrescription');
    }

    public function forceDelete(AuthUser $authUser, OpticalLensNoPrescription $opticalLensNoPrescription): bool
    {
        return $authUser->can('ForceDelete:OpticalLensNoPrescription');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OpticalLensNoPrescription');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OpticalLensNoPrescription');
    }

    public function replicate(AuthUser $authUser, OpticalLensNoPrescription $opticalLensNoPrescription): bool
    {
        return $authUser->can('Replicate:OpticalLensNoPrescription');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OpticalLensNoPrescription');
    }
}
