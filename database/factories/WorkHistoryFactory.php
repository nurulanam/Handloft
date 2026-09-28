<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkHistory>
 */
class WorkHistoryFactory extends Factory
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
            'user_id' => User::factory(),
            'assigned_by' => User::factory(),
            'assigned_date' => now()->toDateString(),
            'completed_date' => now()->toDateString(),
            'completed_time' => now(),
            'actual_hours' => fake()->randomFloat(2, 0.5, 8),
            'status' => 'completed',
            'note' => null,
        ];
    }
}
