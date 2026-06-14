<?php

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Widgets\DueProjectsWidget;
use App\Filament\Projects\Widgets\ProjectStatsOverview;
use Filament\Pages\Dashboard;

class ProjectsDashboardPage extends Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Projects Dashboard';

    protected static string $routePath = '/';

    public function getWidgets(): array
    {
        return [
            ProjectStatsOverview::class,
            DueProjectsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
