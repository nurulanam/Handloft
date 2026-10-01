<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReadyToDeploy extends Notification
{
    public function __construct(private readonly Task $task, private readonly User $approvedBy) {}

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
            ->subject("Task ready to deploy: {$this->task->task_key}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->approvedBy->name} approved \"{$this->task->title}\" — it's ready to deploy and waiting on your sign-off.")
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
            'approved_by' => $this->approvedBy->name,
            'message' => "\"{$this->task->title}\" is ready to deploy — waiting on your sign-off",
            'url' => route('tasks.show', $this->task),
        ];
    }
}
