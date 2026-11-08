<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells every open task board that a task was created, moved, edited or removed, so it can
 * refresh itself. Sent immediately (the app runs without a queue worker) and carries no task
 * data: each browser re-reads what it is allowed to see.
 */
class TasksChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly int $taskId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tasks')];
    }

    public function broadcastAs(): string
    {
        return 'tasks.changed';
    }
}
