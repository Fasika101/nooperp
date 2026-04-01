<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PaymentType;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PaymentType');
    }

    public function view(AuthUser $authUser, PaymentType $paymentType): bool
    {
        return $authUser->can('View:PaymentType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PaymentType');
    }

    public function update(AuthUser $authUser, PaymentType $paymentType): bool
    {
        return $authUser->can('Update:PaymentType');
    }

    public function delete(AuthUser $authUser, PaymentType $paymentType): bool
    {
        return $authUser->can('Delete:PaymentType');
    }

    public function restore(AuthUser $authUser, PaymentType $paymentType): bool
    {
        return $authUser->can('Restore:PaymentType');
    }

    public function forceDelete(AuthUser $authUser, PaymentType $paymentType): bool
    {
        return $authUser->can('ForceDelete:PaymentType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PaymentType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PaymentType');
    }

    public function replicate(AuthUser $authUser, PaymentType $paymentType): bool
    {
        return $authUser->can('Replicate:PaymentType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PaymentType');
    }

}