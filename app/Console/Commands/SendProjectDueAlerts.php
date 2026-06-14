<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use App\Notifications\MilestoneDueNotification;
use App\Notifications\ProjectDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendProjectDueAlerts extends Command
{
    protected $signature   = 'projects:send-due-alerts';
    protected $description = 'Notify project team members when a project or task deadline is approaching or overdue.';

    public function handle(): int
    {
        $this->sendProjectAlerts();
        $this->sendTaskAlerts();
        $this->sendMilestoneAlerts();

        $this->info('Project due alerts dispatched.');

        return self::SUCCESS;
    }

    private function sendProjectAlerts(): void
    {
        // Projects that become overdue exactly today (end_date = yesterday)
        $overdue = Project::query()
            ->whereNotIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED])
            ->whereNotNull('end_date')
            ->whereDate('end_date', now()->subDay()->toDateString())
            ->with(['creator', 'members'])
            ->get();

        foreach ($overdue as $project) {
            $cacheKey = "proj_overdue_{$project->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, true, now()->addDays(7));
            $this->notifyProjectTeam($project, 0, true);
            $this->line("  Overdue alert sent → {$project->name}");
        }

        // Projects due in 1, 3, or 7 days
        foreach ([7, 3, 1] as $days) {
            $dueSoon = Project::query()
                ->whereNotIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED])
                ->whereNotNull('end_date')
                ->whereDate('end_date', now()->addDays($days)->toDateString())
                ->with(['creator', 'members'])
                ->get();

            foreach ($dueSoon as $project) {
                $cacheKey = "proj_due_{$project->id}_{$days}d";
                if (Cache::has($cacheKey)) {
                    continue;
                }
                Cache::put($cacheKey, true, now()->addHours(23));
                $this->notifyProjectTeam($project, $days, false);
                $this->line("  {$days}-day alert sent → {$project->name}");
            }
        }
    }

    private function sendTaskAlerts(): void
    {
        // Tasks due in 1 or 3 days — notify assignees
        foreach ([3, 1] as $days) {
            $tasks = ProjectTask::query()
                ->whereNotNull('due_date')
                ->whereDate('due_date', now()->addDays($days)->toDateString())
                ->with('assignees')
                ->get();

            foreach ($tasks as $task) {
                $cacheKey = "task_due_{$task->id}_{$days}d";
                if (Cache::has($cacheKey)) {
                    continue;
                }
                Cache::put($cacheKey, true, now()->addHours(23));

                foreach ($task->assignees as $user) {
                    $user->notify(
                        new \App\Notifications\TaskDueNotification($task, $days)
                    );
                }
            }
        }
    }

    private function sendMilestoneAlerts(): void
    {
        foreach ([3, 1] as $days) {
            $milestones = ProjectMilestone::query()
                ->whereNull('completed_at')
                ->whereNotNull('due_date')
                ->whereDate('due_date', now()->addDays($days)->toDateString())
                ->with(['project.creator', 'project.members'])
                ->get();

            foreach ($milestones as $milestone) {
                $cacheKey = "milestone_due_{$milestone->id}_{$days}d";
                if (Cache::has($cacheKey)) {
                    continue;
                }
                Cache::put($cacheKey, true, now()->addHours(23));

                if (! $milestone->project) {
                    continue;
                }

                $users = collect([$milestone->project->creator])
                    ->merge($milestone->project->members)
                    ->filter()
                    ->unique('id');

                Notification::send($users, new MilestoneDueNotification($milestone, $days));
                $this->line("  Milestone {$days}-day alert → {$milestone->title}");
            }
        }
    }

    private function notifyProjectTeam(Project $project, int $days, bool $isOverdue): void
    {
        $users = collect([$project->creator])
            ->merge($project->members)
            ->filter()
            ->unique('id');

        Notification::send($users, new \App\Notifications\ProjectDueNotification($project, $days, $isOverdue));
    }
}
