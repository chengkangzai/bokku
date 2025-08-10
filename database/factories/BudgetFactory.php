<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory()->expense(),
            'amount' => $this->faker->randomFloat(2, 100, 2000),
            'period' => $this->faker->randomElement(['weekly', 'monthly', 'annual']),
            'start_date' => now()->startOfMonth(),
            'is_active' => true,
            'auto_rollover' => $this->faker->boolean(30), // 30% chance of auto rollover
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period' => 'weekly',
            'start_date' => now()->startOfWeek(),
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'period' => 'annual',
            'start_date' => now()->startOfYear(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
