<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsInstantly;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectCoordinatorAssigned extends Notification
{
    use BroadcastsInstantly;

    public function __construct(private readonly Project $project, private readonly User $assignedBy) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New project to coordinate: {$this->project->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->assignedBy->name} made you the coordinator for \"{$this->project->name}\".")
            ->action('View Project', route('projects.show', $this->project))
            ->line('Thanks for using '.config('app.name').'!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'assigned_by' => $this->assignedBy->name,
            'message' => "{$this->assignedBy->name} made you the coordinator for \"{$this->project->name}\"",
            'url' => route('projects.show', $this->project),
        ];
    }
}
