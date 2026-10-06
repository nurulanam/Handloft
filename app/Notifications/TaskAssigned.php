<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsInstantly;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use BroadcastsInstantly;

    public function __construct(private readonly Task $task, private readonly User $assignedBy) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New task assigned: {$this->task->task_key}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->assignedBy->name} assigned you a new task: \"{$this->task->title}\".")
            ->action('View Task', route('tasks.show', $this->task))
            ->line('Thanks for using '.config('app.name').'!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_key' => $this->task->task_key,
            'task_title' => $this->task->title,
            'assigned_by' => $this->assignedBy->name,
            'message' => "{$this->assignedBy->name} assigned you \"{$this->task->title}\"",
            'url' => route('tasks.show', $this->task),
        ];
    }
}
