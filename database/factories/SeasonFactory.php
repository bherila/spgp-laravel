<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pass_name' => fake()->word() . ' Pass',
            'pass_year' => fake()->year(),
            'start_date' => now(),
            'early_spring_deadline' => now()->addMonth(),
            'final_deadline' => now()->addMonths(2),
            'spreadsheet_url' => fake()->url(),
        ];
    }
}
