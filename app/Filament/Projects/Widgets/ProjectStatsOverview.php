<?php

namespace App\Filament\Projects\Widgets;

use App\Models\Project;
use App\Models\ProjectBug;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $total = Project::query()
            ->whereNotIn('status', [Project::STATUS_CANCELLED])
            ->count();

        $active = Project::query()
            ->where('status', Project::STATUS_ACTIVE)
            ->count();

        $overdue = Project::query()
            ->whereNotIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED])
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->count();

        $tasksDueThisWeek = ProjectTask::query()
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays(7)->endOfDay())
            ->count();

        $openBugs = ProjectBug::query()
            ->whereIn('status', [ProjectBug::STATUS_OPEN, ProjectBug::STATUS_IN_PROGRESS])
            ->count();

        $pendingMilestones = ProjectMilestone::query()
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays(14)->endOfDay())
            ->count();

        return [
            Stat::make('Total Projects', $total)
                ->description('Excluding cancelled')
                ->descriptionIcon('heroicon-o-folder')
                ->color('primary'),

            Stat::make('Active Projects', $active)
                ->description('Currently in progress')
                ->descriptionIcon('heroicon-o-play-circle')
                ->color('success'),

            Stat::make('Overdue Projects', $overdue)
                ->description('Past their deadline')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success'),

            Stat::make('Tasks Due This Week', $tasksDueThisWeek)
                ->description('Next 7 days')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color($tasksDueThisWeek > 5 ? 'warning' : 'info'),

            Stat::make('Open Bugs', $openBugs)
                ->description('Open & in-progress')
                ->descriptionIcon('heroicon-o-bug-ant')
                ->color($openBugs > 0 ? 'danger' : 'success'),

            Stat::make('Milestones Due (14d)', $pendingMilestones)
                ->description('Upcoming milestones')
                ->descriptionIcon('heroicon-o-flag')
                ->color($pendingMilestones > 0 ? 'warning' : 'success'),
        ];
    }
}
