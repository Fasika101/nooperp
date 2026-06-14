<?php

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use Filament\Pages\Page;

class ProjectCalendarPage extends Page
{
    protected string $view = 'filament.projects.pages.project-calendar';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'My Work';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Project Calendar';

    protected static ?int $navigationSort = 30;

    public function getCalendarEvents(): array
    {
        $uid = auth()->id();
        $events = [];

        // Project deadlines — scoped to user's projects
        Project::query()
            ->whereNotNull('end_date')
            ->whereNotIn('status', [Project::STATUS_CANCELLED])
            ->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            })
            ->get()
            ->each(function (Project $project) use (&$events) {
                $color = match (true) {
                    $project->status === Project::STATUS_COMPLETED => '#6b7280',
                    $project->end_date->isPast()                   => '#ef4444',
                    $project->end_date->lte(now()->addDays(3))     => '#f97316',
                    default                                        => '#8b5cf6',
                };
                $events[] = [
                    'id'              => 'proj-' . $project->id,
                    'title'           => '📁 ' . $project->name,
                    'start'           => $project->end_date->toDateString(),
                    'allDay'          => true,
                    'url'             => ProjectResource::getUrl('edit', ['record' => $project]),
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => ['type' => 'project', 'status' => $project->status],
                ];
            });

        // Task due dates — scoped to user's tasks
        ProjectTask::query()
            ->whereNotNull('due_date')
            ->with('project:id,name')
            ->whereHas('project', function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            })
            ->get()
            ->each(function (ProjectTask $task) use (&$events) {
                $color = match ($task->priority) {
                    'urgent' => '#ef4444',
                    'high'   => '#f97316',
                    'low'    => '#6b7280',
                    default  => '#3b82f6',
                };
                if ($task->due_date->isPast()) {
                    $color = '#dc2626';
                }
                $events[] = [
                    'id'              => 'task-' . $task->id,
                    'title'           => '✓ ' . $task->title,
                    'start'           => $task->due_date->toDateString(),
                    'allDay'          => true,
                    'url'             => ProjectResource::getUrl('edit', ['record' => $task->project_id]),
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => ['type' => 'task', 'project' => $task->project?->name ?? ''],
                ];
            });

        // Milestone due dates — scoped to user's projects
        ProjectMilestone::query()
            ->whereNotNull('due_date')
            ->whereNull('completed_at')
            ->whereHas('project', function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            })
            ->with('project:id,name')
            ->get()
            ->each(function (ProjectMilestone $milestone) use (&$events) {
                $color = $milestone->due_date->isPast() ? '#ef4444' : '#10b981';
                $events[] = [
                    'id'              => 'milestone-' . $milestone->id,
                    'title'           => '🚩 ' . $milestone->title,
                    'start'           => $milestone->due_date->toDateString(),
                    'allDay'          => true,
                    'url'             => ProjectResource::getUrl('edit', ['record' => $milestone->project_id]),
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'type'    => 'milestone',
                        'project' => $milestone->project?->name ?? '',
                    ],
                ];
            });

        return $events;
    }
}
