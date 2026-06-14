<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ProjectsPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ProjectsPanelProvider::class,
];
