<?php

namespace App\Filament\Widgets\Concerns;

use function Filament\Support\original_request;

trait ChecksPageRoute
{
    protected static function isOnFilamentPageRoute(string $routeName): bool
    {
        return original_request()->routeIs($routeName);
    }
}
