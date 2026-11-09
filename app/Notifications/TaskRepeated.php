<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Concerns\BroadcastsInstantly;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A repeating task has come back to To Do for its assignee. */
class TaskRepeated extends Notification
{
    use BroadcastsInstantly;

    public function __construct(private readonly Task $task) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Repeating task is back: {$this->task->task_key}")
            ->greeting("Hi {$notifiable->name},")
            ->line("\"{$this->task->title}\" repeats ({$this->task->repeat_summary}) and is back in To Do.")
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
            'message' => "\"{$this->task->title}\" is back in To Do (repeating task)",
            'url' => route('tasks.show', $this->task),
        ];
    }
}
