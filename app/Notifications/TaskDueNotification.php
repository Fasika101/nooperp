<?php

namespace App\Notifications;

use App\Filament\Projects\Resources\ProjectResource;
use App\Models\ProjectTask;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ProjectTask $task,
        protected int $daysUntilDue
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $label = $this->daysUntilDue === 1 ? 'due tomorrow' : "due in {$this->daysUntilDue} days";
        $title = "Task {$label}: {$this->task->title}";
        $body  = $this->task->project
            ? "Project: {$this->task->project->name} — due {$this->task->due_date->format('M j, Y')}."
            : "Due {$this->task->due_date->format('M j, Y')}.";

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-clipboard-document-list')
            ->color($this->daysUntilDue === 1 ? 'danger' : 'warning')
            ->actions([
                NotificationAction::make('view')
                    ->label('Open Project')
                    ->url(ProjectResource::getUrl('edit', ['record' => $this->task->project_id]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
