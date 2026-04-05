<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BranchStockTransfer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BranchStockTransferPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BranchStockTransfer');
    }

    public function view(AuthUser $authUser, BranchStockTransfer $branchStockTransfer): bool
    {
        return $authUser->can('View:BranchStockTransfer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BranchStockTransfer');
    }

    public function update(AuthUser $authUser, BranchStockTransfer $branchStockTransfer): bool
    {
        return $authUser->can('Update:BranchStockTransfer');
    }

    public function delete(AuthUser $authUser, BranchStockTransfer $branchStockTransfer): bool
    {
        return $authUser->can('Delete:BranchStockTransfer');
    }

    public function restore(AuthUser $authUser, BranchStockTransfer $branchStockTransfer): bool
    {
        return $authUser->can('Restore:BranchStockTransfer');
    }

    public function forceDelete(AuthUser $authUser, BranchStockTransfer $branchStockTransfer): bool
    {
        return $authUser->can('ForceDelete:BranchStockTransfer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BranchStockTransfer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BranchStockTransfer');
    }

    public function replicate(AuthUser $authUser, BranchStockTransfer $branchStockTransfer): bool
    {
        return $authUser->can('Replicate:BranchStockTransfer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BranchStockTransfer');
    }
}
