<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskRejected extends Notification
{
    public function __construct(private readonly Task $task, private readonly User $rejectedBy) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Task rejected: {$this->task->task_key}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->rejectedBy->name} rejected \"{$this->task->title}\" during QA testing.")
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
            'rejected_by' => $this->rejectedBy->name,
            'message' => "{$this->rejectedBy->name} rejected \"{$this->task->title}\"",
            'url' => route('tasks.show', $this->task),
        ];
    }
}
