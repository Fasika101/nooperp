<?php

namespace App\Filament\Projects\Widgets;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class DueProjectsWidget extends Widget
{
    protected string $view = 'filament.projects.widgets.due-projects';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $overdueProjects = Project::query()
            ->with('customer')
            ->whereNotIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED])
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->orderBy('end_date')
            ->take(10)
            ->get();

        $dueSoonProjects = Project::query()
            ->with('customer')
            ->whereNotIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED])
            ->whereNotNull('end_date')
            ->where('end_date', '>=', now()->startOfDay())
            ->where('end_date', '<=', now()->addDays(14)->endOfDay())
            ->orderBy('end_date')
            ->take(10)
            ->get();

        $overdueMilestones = ProjectMilestone::query()
            ->with('project:id,name')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->take(8)
            ->get();

        $dueSoonMilestones = ProjectMilestone::query()
            ->with('project:id,name')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('due_date')
            ->take(8)
            ->get();

        return [
            'overdueProjects'   => $overdueProjects,
            'dueSoonProjects'   => $dueSoonProjects,
            'overdueMilestones' => $overdueMilestones,
            'dueSoonMilestones' => $dueSoonMilestones,
        ];
    }
}
