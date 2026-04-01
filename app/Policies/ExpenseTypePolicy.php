<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ExpenseType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpenseTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExpenseType');
    }

    public function view(AuthUser $authUser, ExpenseType $expenseType): bool
    {
        return $authUser->can('View:ExpenseType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExpenseType');
    }

    public function update(AuthUser $authUser, ExpenseType $expenseType): bool
    {
        return $authUser->can('Update:ExpenseType');
    }

    public function delete(AuthUser $authUser, ExpenseType $expenseType): bool
    {
        return $authUser->can('Delete:ExpenseType');
    }

    public function restore(AuthUser $authUser, ExpenseType $expenseType): bool
    {
        return $authUser->can('Restore:ExpenseType');
    }

    public function forceDelete(AuthUser $authUser, ExpenseType $expenseType): bool
    {
        return $authUser->can('ForceDelete:ExpenseType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExpenseType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExpenseType');
    }

    public function replicate(AuthUser $authUser, ExpenseType $expenseType): bool
    {
        return $authUser->can('Replicate:ExpenseType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExpenseType');
    }

}