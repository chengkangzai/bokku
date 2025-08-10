<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $types = ['income', 'expense'];
        $type = fake()->randomElement($types);

        $incomeCategories = [
            'Salary', 'Freelance', 'Investment', 'Business', 'Rental Income',
            'Bonus', 'Commission', 'Interest', 'Dividend', 'Gift',
        ];

        $expenseCategories = [
            'Food & Dining', 'Transportation', 'Shopping', 'Entertainment',
            'Utilities', 'Healthcare', 'Education', 'Travel', 'Insurance',
            'Groceries', 'Rent', 'Internet', 'Phone', 'Fuel', 'Clothing',
        ];

        $categoryName = $type === 'income'
            ? fake()->randomElement($incomeCategories)
            : fake()->randomElement($expenseCategories);

        return [
            'user_id' => User::factory(),
            'name' => $categoryName,
            'type' => $type,
            'icon' => null,
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function income(): static
    {
        $incomeCategories = [
            'Salary', 'Freelance', 'Investment', 'Business', 'Rental Income',
            'Bonus', 'Commission', 'Interest', 'Dividend', 'Gift',
        ];

        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'name' => fake()->randomElement($incomeCategories),
        ]);
    }

    public function expense(): static
    {
        $expenseCategories = [
            'Food & Dining', 'Transportation', 'Shopping', 'Entertainment',
            'Utilities', 'Healthcare', 'Education', 'Travel', 'Insurance',
            'Groceries', 'Rent', 'Internet', 'Phone', 'Fuel', 'Clothing',
        ];

        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'name' => fake()->randomElement($expenseCategories),
        ]);
    }

    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $sortOrder,
        ]);
    }
}
