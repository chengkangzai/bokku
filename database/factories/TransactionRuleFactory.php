<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionRule>
 */
class TransactionRuleFactory extends Factory
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
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => $this->faker->company(),
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => $this->faker->word(),
                ],
            ],
            'priority' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
            'stop_processing' => false,
            'apply_to' => 'all', // Default to all for testing consistency
            'times_applied' => 0,
            'last_applied_at' => null,
        ];
    }

    /**
     * Indicate that the rule should categorize transactions.
     */
    public function withCategoryAction($categoryId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => [
                [
                    'type' => 'set_category',
                    'category_id' => $categoryId ?? Category::factory(),
                ],
            ],
        ]);
    }

    /**
     * Indicate that the rule should match by amount.
     */
    public function withAmountCondition($operator = 'greater_than', $amount = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => $operator,
                    'value' => $amount,
                ],
            ],
        ]);
    }

    /**
     * Indicate that the rule is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the rule stops processing.
     */
    public function stopsProcessing(): static
    {
        return $this->state(fn (array $attributes) => [
            'stop_processing' => true,
        ]);
    }
}
