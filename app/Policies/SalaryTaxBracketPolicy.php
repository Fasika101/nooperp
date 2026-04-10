<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalaryTaxBracket;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SalaryTaxBracketPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SalaryTaxBracket');
    }

    public function view(AuthUser $authUser, SalaryTaxBracket $salaryTaxBracket): bool
    {
        return $authUser->can('View:SalaryTaxBracket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SalaryTaxBracket');
    }

    public function update(AuthUser $authUser, SalaryTaxBracket $salaryTaxBracket): bool
    {
        return $authUser->can('Update:SalaryTaxBracket');
    }

    public function delete(AuthUser $authUser, SalaryTaxBracket $salaryTaxBracket): bool
    {
        return $authUser->can('Delete:SalaryTaxBracket');
    }

    public function restore(AuthUser $authUser, SalaryTaxBracket $salaryTaxBracket): bool
    {
        return $authUser->can('Restore:SalaryTaxBracket');
    }

    public function forceDelete(AuthUser $authUser, SalaryTaxBracket $salaryTaxBracket): bool
    {
        return $authUser->can('ForceDelete:SalaryTaxBracket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SalaryTaxBracket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SalaryTaxBracket');
    }

    public function replicate(AuthUser $authUser, SalaryTaxBracket $salaryTaxBracket): bool
    {
        return $authUser->can('Replicate:SalaryTaxBracket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SalaryTaxBracket');
    }
}
