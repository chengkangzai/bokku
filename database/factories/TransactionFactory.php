<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $types = [TransactionType::Income, TransactionType::Expense];
        $type = fake()->randomElement($types);

        $descriptions = [
            TransactionType::Income->value => [
                'Salary Payment', 'Freelance Project', 'Investment Return',
                'Business Revenue', 'Bonus Payment', 'Commission', 'Interest Earned',
            ],
            TransactionType::Expense->value => [
                'Grocery Shopping', 'Restaurant Bill', 'Gas Station', 'Online Purchase',
                'Utility Payment', 'Medical Expenses', 'Insurance Premium', 'Rent Payment',
            ],
        ];

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'amount' => fake()->numberBetween(10, 1000), // $10 to $1000
            'description' => fake()->randomElement($descriptions[$type->value]),
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'account_id' => Account::factory(),
            'category_id' => Category::factory()->state(['type' => $type->value]),
            'from_account_id' => null,
            'to_account_id' => null,
            'reference' => fake()->optional(0.3)->regexify('[A-Z]{3}[0-9]{6}'),
            'notes' => fake()->optional(0.4)->sentence(),
            'is_reconciled' => fake()->boolean(70),
        ];
    }

    public function income(): static
    {
        $descriptions = [
            'Salary Payment', 'Freelance Project', 'Investment Return',
            'Business Revenue', 'Bonus Payment', 'Commission', 'Interest Earned',
        ];

        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
            'description' => fake()->randomElement($descriptions),
            'amount' => fake()->numberBetween(100, 5000), // $100 to $5000
            'category_id' => Category::factory()->income(),
        ]);
    }

    public function expense(): static
    {
        $descriptions = [
            'Grocery Shopping', 'Restaurant Bill', 'Gas Station', 'Online Purchase',
            'Utility Payment', 'Medical Expenses', 'Insurance Premium', 'Rent Payment',
        ];

        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Expense,
            'description' => fake()->randomElement($descriptions),
            'amount' => fake()->numberBetween(5, 500), // $5 to $500
            'category_id' => Category::factory()->expense(),
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Transfer,
            'description' => 'Transfer between accounts',
            'amount' => fake()->numberBetween(50, 2000), // $50 to $2000
        ]);
    }

    public function withAmount(float $amountInDollars): static
    {
        // MoneyCast handles conversion from dollars to cents
        return $this->state(fn (array $attributes) => [
            'amount' => $amountInDollars, // Pass dollars, cast converts to cents
        ]);
    }

    public function withDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    public function reconciled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reconciled' => true,
        ]);
    }

    public function notReconciled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reconciled' => false,
        ]);
    }

    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween(now()->startOfMonth(), now()->endOfMonth()),
        ]);
    }

    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            ),
        ]);
    }

    /**
     * Configure the model factory to attach media files after creation.
     * This is used for testing media attachment functionality.
     */
    public function withMedia(): static
    {
        return $this->afterCreating(function (Transaction $transaction) {
            // Create a temporary test image file
            $tempFile = tempnam(sys_get_temp_dir(), 'test_receipt_');

            // Create a simple 1x1 pixel PNG image
            $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
            file_put_contents($tempFile, $imageData);

            // Rename to have proper extension
            $imageFile = $tempFile.'.png';
            rename($tempFile, $imageFile);

            // Attach the file to the transaction
            $transaction->addMedia($imageFile)
                ->usingName('Test Receipt')
                ->usingFileName('test_receipt_'.fake()->uuid().'.png')
                ->toMediaCollection('receipts');

            // Clean up temp file (media library copies it)
            @unlink($imageFile);
        });
    }

    /**
     * Configure the model factory to attach multiple media files.
     */
    public function withMultipleMedia(int $count = 3): static
    {
        return $this->afterCreating(function (Transaction $transaction) use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                // Create a temporary test image file
                $tempFile = tempnam(sys_get_temp_dir(), 'test_receipt_');

                // Create a simple 1x1 pixel PNG image
                $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
                file_put_contents($tempFile, $imageData);

                // Rename to have proper extension
                $imageFile = $tempFile.'.png';
                rename($tempFile, $imageFile);

                // Attach the file to the transaction
                $transaction->addMedia($imageFile)
                    ->usingName("Test Receipt {$i}")
                    ->usingFileName("test_receipt_{$i}_".fake()->uuid().'.png')
                    ->toMediaCollection('receipts');

                // Clean up temp file
                @unlink($imageFile);
            }
        });
    }
}
