<?php

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ProjectKanbanPage extends Page
{
    protected string $view = 'filament.projects.pages.project-kanban';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static string|\UnitEnum|null $navigationGroup = 'My Work';

    protected static ?string $navigationLabel = 'Kanban Board';

    protected static ?string $title = 'Kanban Board';

    protected static ?int $navigationSort = 25;

    public ?int $selectedProjectId = null;

    public function mount(): void
    {
        $this->selectedProjectId = request()->integer('project') ?: null;
    }

    public function moveTask(int $taskId, int $newStageId): void
    {
        ProjectTask::where('id', $taskId)->update(['project_task_stage_id' => $newStageId]);
    }

    public function getProjects(): Collection
    {
        $uid = auth()->id();

        return Project::query()
            ->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getBoardData(): array
    {
        $stages = ProjectTaskStage::orderBy('position')->get(['id', 'name']);

        $query = ProjectTask::with(['project:id,name', 'assignees:id,name'])
            ->whereHas('project');

        if ($this->selectedProjectId) {
            $query->where('project_id', $this->selectedProjectId);
        } else {
            // Scope to projects the user is on
            $uid = auth()->id();
            $query->whereHas('project', function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            });
        }

        $tasks = $query->get();

        return [
            'stages' => $stages->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
            ])->values()->toArray(),
            'tasks' => $tasks->map(fn ($t) => [
                'id'        => $t->id,
                'title'     => $t->title,
                'stageId'   => $t->project_task_stage_id,
                'priority'  => $t->priority,
                'project'   => $t->project?->name ?? '',
                'projectId' => $t->project_id,
                'dueDate'   => $t->due_date?->format('M j'),
                'isOverdue' => $t->due_date?->isPast() ?? false,
                'isDueSoon' => $t->due_date !== null
                    && ! $t->due_date->isPast()
                    && $t->due_date->lte(now()->addDays(3)),
                'editUrl'   => ProjectResource::getUrl('edit', ['record' => $t->project_id]),
                'assignees' => $t->assignees->pluck('name')->implode(', '),
            ])->values()->toArray(),
        ];
    }
}
