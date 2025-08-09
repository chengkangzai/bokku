<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $types = ['bank', 'cash', 'credit_card', 'loan'];
        $currencies = ['MYR', 'USD', 'EUR', 'GBP', 'JPY'];
        $initialBalance = fake()->randomFloat(2, 0, 10000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->company() . ' ' . fake()->randomElement(['Checking', 'Savings', 'Account']),
            'type' => fake()->randomElement($types),
            'icon' => null,
            'color' => fake()->hexColor(),
            'balance' => $initialBalance,
            'initial_balance' => $initialBalance,
            'currency' => fake()->randomElement($currencies),
            'account_number' => fake()->numerify('****####'),
            'notes' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }

    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank',
            'name' => fake()->company() . ' Bank Account',
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cash',
            'name' => 'Cash Wallet',
            'account_number' => null,
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit_card',
            'name' => fake()->company() . ' Credit Card',
            'initial_balance' => 0,
            'balance' => fake()->randomFloat(2, -5000, 0),
        ]);
    }

    public function loan(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'loan',
            'name' => fake()->randomElement(['Home', 'Car', 'Personal']) . ' Loan',
            'initial_balance' => fake()->randomFloat(2, -50000, -1000),
            'balance' => fake()->randomFloat(2, -50000, -1000),
        ]);
    }

    public function withCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}