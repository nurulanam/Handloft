<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'status' => TaskStatus::Todo,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
            'notes' => null,
        ];
    }
}
