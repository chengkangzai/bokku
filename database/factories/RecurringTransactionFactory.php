<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([TransactionType::Income, TransactionType::Expense, TransactionType::Transfer]);
        $frequency = $this->faker->randomElement(['daily', 'weekly', 'monthly', 'annual']);

        $data = [
            'user_id' => User::factory(),
            'type' => $type,
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'description' => $this->faker->randomElement([
                'Rent Payment',
                'Salary',
                'Netflix Subscription',
                'Electricity Bill',
                'Internet Bill',
                'Gym Membership',
                'Insurance Premium',
                'Car Payment',
                'Phone Bill',
                'Spotify Subscription',
            ]),
            'account_id' => Account::factory(),
            'category_id' => $type !== TransactionType::Transfer ? Category::factory() : null,
            'to_account_id' => $type === TransactionType::Transfer ? Account::factory() : null,
            'frequency' => $frequency,
            'interval' => $this->faker->randomElement([1, 1, 1, 2]), // Mostly 1, sometimes 2
            'day_of_week' => null,
            'day_of_month' => null,
            'month_of_year' => null,
            'next_date' => now()->addDays($this->faker->numberBetween(1, 30)),
            'last_processed' => null,
            'start_date' => now()->subDays($this->faker->numberBetween(0, 365)),
            'end_date' => $this->faker->optional(0.3)->dateTimeBetween('now', '+2 years'),
            'is_active' => true,
            'auto_process' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];

        // Set specific day fields based on frequency
        if ($frequency === 'weekly') {
            $data['day_of_week'] = $this->faker->numberBetween(0, 6); // Carbon standard: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
        } elseif ($frequency === 'monthly') {
            $data['day_of_month'] = $this->faker->numberBetween(1, 28);
        } elseif ($frequency === 'annual') {
            $data['month_of_year'] = $this->faker->numberBetween(1, 12);
            $data['day_of_month'] = $this->faker->numberBetween(1, 28);
        }

        return $data;
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
            'category_id' => Category::factory()->income(),
            'to_account_id' => null,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Expense,
            'category_id' => Category::factory()->expense(),
            'to_account_id' => null,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Transfer,
            'category_id' => null,
            'to_account_id' => Account::factory(),
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'daily',
            'day_of_week' => null,
            'day_of_month' => null,
            'month_of_year' => null,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'weekly',
            'day_of_week' => $this->faker->numberBetween(0, 6), // Carbon standard: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
            'day_of_month' => null,
            'month_of_year' => null,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'monthly',
            'day_of_week' => null,
            'day_of_month' => $this->faker->numberBetween(1, 28),
            'month_of_year' => null,
            'next_date' => now()->startOfMonth()->addDays($this->faker->numberBetween(0, 27)),
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'annual',
            'day_of_week' => null,
            'day_of_month' => $this->faker->numberBetween(1, 28),
            'month_of_year' => $this->faker->numberBetween(1, 12),
            'next_date' => now()->startOfYear()->addMonths($this->faker->numberBetween(0, 11)),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_date' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    public function upcoming(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'next_date' => now()->addDays($this->faker->numberBetween(1, $days)),
            'is_active' => true,
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    public function withDescription(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $description,
        ]);
    }
}
