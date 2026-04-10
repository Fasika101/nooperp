<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OpticalLensRxLensType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OpticalLensRxLensTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OpticalLensRxLensType');
    }

    public function view(AuthUser $authUser, OpticalLensRxLensType $opticalLensRxLensType): bool
    {
        return $authUser->can('View:OpticalLensRxLensType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OpticalLensRxLensType');
    }

    public function update(AuthUser $authUser, OpticalLensRxLensType $opticalLensRxLensType): bool
    {
        return $authUser->can('Update:OpticalLensRxLensType');
    }

    public function delete(AuthUser $authUser, OpticalLensRxLensType $opticalLensRxLensType): bool
    {
        return $authUser->can('Delete:OpticalLensRxLensType');
    }

    public function restore(AuthUser $authUser, OpticalLensRxLensType $opticalLensRxLensType): bool
    {
        return $authUser->can('Restore:OpticalLensRxLensType');
    }

    public function forceDelete(AuthUser $authUser, OpticalLensRxLensType $opticalLensRxLensType): bool
    {
        return $authUser->can('ForceDelete:OpticalLensRxLensType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OpticalLensRxLensType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OpticalLensRxLensType');
    }

    public function replicate(AuthUser $authUser, OpticalLensRxLensType $opticalLensRxLensType): bool
    {
        return $authUser->can('Replicate:OpticalLensRxLensType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OpticalLensRxLensType');
    }
}
