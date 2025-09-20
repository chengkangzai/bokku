<?php

namespace Database\Factories;

use App\Enums\AccountType;
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
        $currencies = ['MYR', 'USD', 'EUR', 'GBP', 'JPY'];
        $initialBalance = fake()->numberBetween(0, 10000); // $0 to $10,000

        return [
            'user_id' => User::factory(),
            'name' => fake()->company().' '.fake()->randomElement(['Checking', 'Savings', 'Account']),
            'type' => fake()->randomElement(AccountType::cases()),
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
            'type' => AccountType::Bank,
            'name' => fake()->company().' Bank Account',
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Cash,
            'name' => 'Cash Wallet',
            'account_number' => null,
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::CreditCard,
            'name' => fake()->company().' Credit Card',
            'initial_balance' => 0,
            'balance' => fake()->numberBetween(0, 5000), // $0 to $5000 representing amount owed
        ]);
    }

    public function loan(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::Loan,
            'name' => fake()->randomElement(['Home', 'Car', 'Personal']).' Loan',
            'initial_balance' => fake()->numberBetween(1000, 50000), // $1,000 to $50,000 representing amount owed
            'balance' => fake()->numberBetween(1000, 50000), // $1,000 to $50,000 representing amount owed
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
