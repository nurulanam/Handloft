<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAttachment>
 */
class TaskAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'uploaded_by' => User::factory(),
            'path' => 'task-attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
