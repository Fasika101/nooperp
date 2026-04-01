<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StockPurchase;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockPurchasePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StockPurchase');
    }

    public function view(AuthUser $authUser, StockPurchase $stockPurchase): bool
    {
        return $authUser->can('View:StockPurchase');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StockPurchase');
    }

    public function update(AuthUser $authUser, StockPurchase $stockPurchase): bool
    {
        return $authUser->can('Update:StockPurchase');
    }

    public function delete(AuthUser $authUser, StockPurchase $stockPurchase): bool
    {
        return $authUser->can('Delete:StockPurchase');
    }

    public function restore(AuthUser $authUser, StockPurchase $stockPurchase): bool
    {
        return $authUser->can('Restore:StockPurchase');
    }

    public function forceDelete(AuthUser $authUser, StockPurchase $stockPurchase): bool
    {
        return $authUser->can('ForceDelete:StockPurchase');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StockPurchase');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StockPurchase');
    }

    public function replicate(AuthUser $authUser, StockPurchase $stockPurchase): bool
    {
        return $authUser->can('Replicate:StockPurchase');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StockPurchase');
    }

}