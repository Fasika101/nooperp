<?php

namespace App\Notifications;

use App\Filament\Projects\Resources\ProjectResource;
use App\Models\Project;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Project $project,
        protected int $daysUntilDue,
        protected bool $isOverdue = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        if ($this->isOverdue) {
            $title = "Project overdue: {$this->project->name}";
            $body  = "This project was due on {$this->project->end_date->format('M j, Y')} and is now overdue.";
            $color = 'danger';
            $icon  = 'heroicon-o-exclamation-triangle';
        } elseif ($this->daysUntilDue === 1) {
            $title = "Due tomorrow: {$this->project->name}";
            $body  = "This project is due tomorrow ({$this->project->end_date->format('M j, Y')}).";
            $color = 'danger';
            $icon  = 'heroicon-o-clock';
        } elseif ($this->daysUntilDue <= 3) {
            $title = "Due in {$this->daysUntilDue} days: {$this->project->name}";
            $body  = "Deadline: {$this->project->end_date->format('M j, Y')}.";
            $color = 'warning';
            $icon  = 'heroicon-o-clock';
        } else {
            $title = "Coming up in {$this->daysUntilDue} days: {$this->project->name}";
            $body  = "Deadline: {$this->project->end_date->format('M j, Y')}.";
            $color = 'info';
            $icon  = 'heroicon-o-calendar-days';
        }

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->color($color)
            ->actions([
                NotificationAction::make('view')
                    ->label('Open Project')
                    ->url(ProjectResource::getUrl('edit', ['record' => $this->project]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->isOverdue
            ? "⚠️ Overdue: {$this->project->name}"
            : "⏰ Due in {$this->daysUntilDue} days: {$this->project->name}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->isOverdue
                ? "The project **{$this->project->name}** was due on {$this->project->end_date->format('M j, Y')} and is now overdue."
                : "The project **{$this->project->name}** is due in {$this->daysUntilDue} " . str('day')->plural($this->daysUntilDue) . " on {$this->project->end_date->format('M j, Y')}.")
            ->action('View Project', ProjectResource::getUrl('edit', ['record' => $this->project]))
            ->line('Log in to Liba Projects to review and update the project status.');
    }
}
