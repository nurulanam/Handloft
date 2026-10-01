<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskSubmittedForQa extends Notification
{
    public function __construct(private readonly Task $task, private readonly User $submittedBy) {}

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
            ->subject("New task to test: {$this->task->task_key}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->submittedBy->name} submitted \"{$this->task->title}\" for QA testing.")
            ->action('Review Task', route('tasks.show', $this->task))
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
            'submitted_by' => $this->submittedBy->name,
            'message' => "{$this->submittedBy->name} submitted \"{$this->task->title}\" for QA testing",
            'url' => route('tasks.show', $this->task),
        ];
    }
}
