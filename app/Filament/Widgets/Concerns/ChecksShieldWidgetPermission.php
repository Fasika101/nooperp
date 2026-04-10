<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Facades\Filament;

trait ChecksShieldWidgetPermission
{
    protected static function hasWidgetPermission(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : true;
    }
}
