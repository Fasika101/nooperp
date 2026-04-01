<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TelegramBotChat;
use Illuminate\Auth\Access\HandlesAuthorization;

class TelegramBotChatPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TelegramBotChat');
    }

    public function view(AuthUser $authUser, TelegramBotChat $telegramBotChat): bool
    {
        return $authUser->can('View:TelegramBotChat');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TelegramBotChat');
    }

    public function update(AuthUser $authUser, TelegramBotChat $telegramBotChat): bool
    {
        return $authUser->can('Update:TelegramBotChat');
    }

    public function delete(AuthUser $authUser, TelegramBotChat $telegramBotChat): bool
    {
        return $authUser->can('Delete:TelegramBotChat');
    }

    public function restore(AuthUser $authUser, TelegramBotChat $telegramBotChat): bool
    {
        return $authUser->can('Restore:TelegramBotChat');
    }

    public function forceDelete(AuthUser $authUser, TelegramBotChat $telegramBotChat): bool
    {
        return $authUser->can('ForceDelete:TelegramBotChat');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TelegramBotChat');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TelegramBotChat');
    }

    public function replicate(AuthUser $authUser, TelegramBotChat $telegramBotChat): bool
    {
        return $authUser->can('Replicate:TelegramBotChat');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TelegramBotChat');
    }

}