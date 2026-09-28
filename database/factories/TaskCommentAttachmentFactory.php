<?php

namespace Database\Factories;

use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskCommentAttachment>
 */
class TaskCommentAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_comment_id' => TaskComment::factory(),
            'uploaded_by' => User::factory(),
            'path' => 'task-comment-attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
