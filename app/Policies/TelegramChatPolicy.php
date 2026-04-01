<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TelegramChat;
use Illuminate\Auth\Access\HandlesAuthorization;

class TelegramChatPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TelegramChat');
    }

    public function view(AuthUser $authUser, TelegramChat $telegramChat): bool
    {
        return $authUser->can('View:TelegramChat');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TelegramChat');
    }

    public function update(AuthUser $authUser, TelegramChat $telegramChat): bool
    {
        return $authUser->can('Update:TelegramChat');
    }

    public function delete(AuthUser $authUser, TelegramChat $telegramChat): bool
    {
        return $authUser->can('Delete:TelegramChat');
    }

    public function restore(AuthUser $authUser, TelegramChat $telegramChat): bool
    {
        return $authUser->can('Restore:TelegramChat');
    }

    public function forceDelete(AuthUser $authUser, TelegramChat $telegramChat): bool
    {
        return $authUser->can('ForceDelete:TelegramChat');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TelegramChat');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TelegramChat');
    }

    public function replicate(AuthUser $authUser, TelegramChat $telegramChat): bool
    {
        return $authUser->can('Replicate:TelegramChat');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TelegramChat');
    }

}