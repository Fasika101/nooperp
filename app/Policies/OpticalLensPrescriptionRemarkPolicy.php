<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OpticalLensPrescriptionRemark;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OpticalLensPrescriptionRemarkPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OpticalLensPrescriptionRemark');
    }

    public function view(AuthUser $authUser, OpticalLensPrescriptionRemark $opticalLensPrescriptionRemark): bool
    {
        return $authUser->can('View:OpticalLensPrescriptionRemark');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OpticalLensPrescriptionRemark');
    }

    public function update(AuthUser $authUser, OpticalLensPrescriptionRemark $opticalLensPrescriptionRemark): bool
    {
        return $authUser->can('Update:OpticalLensPrescriptionRemark');
    }

    public function delete(AuthUser $authUser, OpticalLensPrescriptionRemark $opticalLensPrescriptionRemark): bool
    {
        return $authUser->can('Delete:OpticalLensPrescriptionRemark');
    }

    public function restore(AuthUser $authUser, OpticalLensPrescriptionRemark $opticalLensPrescriptionRemark): bool
    {
        return $authUser->can('Restore:OpticalLensPrescriptionRemark');
    }

    public function forceDelete(AuthUser $authUser, OpticalLensPrescriptionRemark $opticalLensPrescriptionRemark): bool
    {
        return $authUser->can('ForceDelete:OpticalLensPrescriptionRemark');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OpticalLensPrescriptionRemark');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OpticalLensPrescriptionRemark');
    }

    public function replicate(AuthUser $authUser, OpticalLensPrescriptionRemark $opticalLensPrescriptionRemark): bool
    {
        return $authUser->can('Replicate:OpticalLensPrescriptionRemark');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OpticalLensPrescriptionRemark');
    }
}
