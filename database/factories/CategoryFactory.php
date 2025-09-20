<?php

namespace Database\Factories;

use App\Enums\TransactionType;
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
        $types = [TransactionType::Income->value, TransactionType::Expense->value];
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

        $categoryName = $type === TransactionType::Income->value
            ? fake()->randomElement($incomeCategories)
            : fake()->randomElement($expenseCategories);

        // Add unique suffix to prevent duplicates in parallel tests
        $uniqueSuffix = fake()->unique()->numberBetween(1000, 99999);
        $categoryName = $categoryName.'_'.$uniqueSuffix;

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
            'type' => TransactionType::Income->value,
            'name' => fake()->randomElement($incomeCategories).'_'.fake()->unique()->numberBetween(1000, 99999),
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
            'type' => TransactionType::Expense->value,
            'name' => fake()->randomElement($expenseCategories).'_'.fake()->unique()->numberBetween(1000, 99999),
        ]);
    }

    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $sortOrder,
        ]);
    }
}
