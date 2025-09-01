<?php

use App\Filament\Widgets\StatsOverview;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('StatsOverview Widget Instantiation', function () {
    it('can be instantiated', function () {
        $widget = new StatsOverview;
        expect($widget)->toBeInstanceOf(StatsOverview::class);
    });

    it('has correct sort order', function () {
        $reflectionClass = new ReflectionClass(StatsOverview::class);
        $sortProperty = $reflectionClass->getProperty('sort');
        $sortProperty->setAccessible(true);

        expect($sortProperty->getValue())->toBe(1);
    });
});

describe('StatsOverview Widget Rendering', function () {
    it('can render successfully', function () {
        livewire(StatsOverview::class)
            ->assertSuccessful();
    });

    it('can render without data', function () {
        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('Net Worth')
            ->assertSee('Monthly Income')
            ->assertSee('Monthly Expenses')
            ->assertSee('Monthly Savings');
    });

    it('displays stats with user data', function () {
        // Create test accounts and transactions
        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'bank',
            'balance' => 1000.00,
        ]);

        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'cash',
            'balance' => 500.00,
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 3000.00,
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 1200.00,
            'date' => now(),
        ]);

        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('1,500.00') // Net worth
            ->assertSee('3,000.00') // Monthly income
            ->assertSee('1,200.00') // Monthly expenses
            ->assertSee('1,800.00'); // Monthly savings
    });
});

describe('StatsOverview Data Scoping', function () {
    it('only shows data for authenticated user', function () {
        $otherUser = User::factory()->create();

        // Create data for current user
        Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 2000.00,
            'date' => now(),
        ]);

        // Create data for other user (should not be included)
        Account::factory()->create([
            'user_id' => $otherUser->id,
            'balance' => 5000.00,
        ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'income',
            'amount' => 10000.00,
            'date' => now(),
        ]);

        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('1,000.00') // Only current user's net worth
            ->assertSee('2,000.00') // Only current user's income
            ->assertDontSee('5,000.00') // Other user's data should not appear
            ->assertDontSee('10,000.00');
    });
});

describe('StatsOverview Monthly Calculations', function () {
    it('calculates net worth correctly', function () {
        // Assets
        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'bank',
            'balance' => 1500.00,
        ]);

        // Liabilities (stored as positive values)
        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'balance' => 500.00, // Credit card debt stored as positive
        ]);

        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('1,000.00'); // Net worth: 1500 - 500 = 1000
    });

    it('calculates monthly savings correctly', function () {
        // Current month income
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 2000.00,
            'date' => now(),
        ]);

        // Current month expenses
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 800.00,
            'date' => now(),
        ]);

        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('1,200.00'); // Savings: 2000 - 800 = 1200
    });

    it('handles negative savings (overspending)', function () {
        // Lower income than expenses
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 1000.00,
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 1500.00,
            'date' => now(),
        ]);

        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('-500'); // Negative savings: 1000 - 1500 = -500 (without .00)
    });
});

describe('StatsOverview Zero Values', function () {
    it('handles zero net worth', function () {
        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('0.00'); // Should show 0.00 for net worth when no accounts
    });

    it('handles zero income and expenses', function () {
        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee('Monthly Income')
            ->assertSee('Monthly Expenses')
            ->assertSee('0.00'); // Should show 0.00 for income/expenses when no transactions
    });
});

describe('StatsOverview Month Display', function () {
    it('shows current month name in descriptions', function () {
        $currentMonth = now()->format('F Y');

        livewire(StatsOverview::class)
            ->assertSuccessful()
            ->assertSee($currentMonth); // Should show current month name
    });
});
