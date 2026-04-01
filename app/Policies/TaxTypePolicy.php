<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TaxType;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaxTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TaxType');
    }

    public function view(AuthUser $authUser, TaxType $taxType): bool
    {
        return $authUser->can('View:TaxType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TaxType');
    }

    public function update(AuthUser $authUser, TaxType $taxType): bool
    {
        return $authUser->can('Update:TaxType');
    }

    public function delete(AuthUser $authUser, TaxType $taxType): bool
    {
        return $authUser->can('Delete:TaxType');
    }

    public function restore(AuthUser $authUser, TaxType $taxType): bool
    {
        return $authUser->can('Restore:TaxType');
    }

    public function forceDelete(AuthUser $authUser, TaxType $taxType): bool
    {
        return $authUser->can('ForceDelete:TaxType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TaxType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TaxType');
    }

    public function replicate(AuthUser $authUser, TaxType $taxType): bool
    {
        return $authUser->can('Replicate:TaxType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TaxType');
    }

}