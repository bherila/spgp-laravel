<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeasonPassType>
 */
class SeasonPassTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'pass_type_name' => $this->faker->word() . ' Pass',
            'regular_early_price' => 500.00,
            'regular_regular_price' => 600.00,
            'renewal_early_price' => 450.00,
            'renewal_regular_price' => 550.00,
            'group_early_price' => 400.00,
            'group_regular_price' => 500.00,
            'sort_order' => 0,
        ];
    }
}
