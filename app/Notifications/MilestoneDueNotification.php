<?php

namespace App\Notifications;

use App\Filament\Projects\Resources\ProjectResource;
use App\Models\ProjectMilestone;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MilestoneDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ProjectMilestone $milestone,
        protected int $daysUntilDue
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $label   = $this->daysUntilDue === 1 ? 'due tomorrow' : "due in {$this->daysUntilDue} days";
        $title   = "Milestone {$label}: {$this->milestone->title}";
        $project = $this->milestone->project?->name ?? '';
        $body    = $project
            ? "Project: {$project} — due {$this->milestone->due_date->format('M j, Y')}."
            : "Due {$this->milestone->due_date->format('M j, Y')}.";

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-flag')
            ->color($this->daysUntilDue === 1 ? 'danger' : 'warning')
            ->actions([
                NotificationAction::make('view')
                    ->label('Open Project')
                    ->url(ProjectResource::getUrl('edit', ['record' => $this->milestone->project_id]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
