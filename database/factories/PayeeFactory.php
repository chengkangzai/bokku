<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payee>
 */
class PayeeFactory extends Factory
{
    protected $model = Payee::class;

    public function definition(): array
    {
        $payeeNames = [
            'Starbucks', 'McDonald\'s', 'Amazon', 'Walmart', 'Target',
            'Shell Gas Station', 'Spotify', 'Netflix', 'Electric Company',
            'Water Utility', 'Internet Provider', 'Gym Membership',
            'Grab', 'Shopee', 'Lazada', 'Uniqlo', 'Daiso', 'IKEA',
            'Petronas', 'Maybank', 'TnG eWallet', 'Apple', 'Google',
        ];

        // Add unique suffix to prevent duplicates in parallel tests
        $uniqueSuffix = fake()->unique()->numberBetween(1000, 99999);
        $payeeName = fake()->randomElement($payeeNames).'_'.$uniqueSuffix;

        return [
            'user_id' => User::factory(),
            'name' => $payeeName,
            'default_category_id' => null,
            'is_active' => true,
        ];
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

    public function withDefaultCategory(): static
    {
        return $this->state(function (array $attributes) {
            $userId = $attributes['user_id'];

            // If user_id is a factory, we need to create it first
            if ($userId instanceof \Closure) {
                $userId = User::factory()->create()->id;
            } elseif ($userId instanceof User) {
                $userId = $userId->id;
            }

            return [
                'user_id' => $userId,
                'default_category_id' => Category::factory()->expense()->create(['user_id' => $userId])->id,
            ];
        });
    }
}
