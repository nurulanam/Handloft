<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Active,
            'created_by' => User::factory(),
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonth()->toDateString(),
        ];
    }
}
