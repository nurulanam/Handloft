<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsInstantly;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskRejected extends Notification
{
    use BroadcastsInstantly;

    public function __construct(private readonly Task $task, private readonly User $rejectedBy, private readonly ?string $reason = null) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Task rejected: {$this->task->task_key}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->rejectedBy->name} rejected \"{$this->task->title}\" during QA testing.");

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        return $mail->action('View Task', route('tasks.show', $this->task))
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
            'reason' => $this->reason,
            'url' => route('tasks.show', $this->task),
        ];
    }
}
