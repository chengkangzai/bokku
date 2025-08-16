<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

describe('Budget Model', function () {
    it('can be created with factory', function () {
        $budget = Budget::factory()->create();

        expect($budget)
            ->toBeInstanceOf(Budget::class)
            ->and($budget->user_id)->toBeInt()
            ->and($budget->category_id)->toBeInt()
            ->and($budget->amount)->toBeFloat()
            ->and($budget->period)->toBeIn(['weekly', 'monthly', 'annual'])
            ->and($budget->start_date)->toBeInstanceOf(Carbon::class)
            ->and($budget->is_active)->toBeBool();
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id]);

        expect($budget->user->id)->toBe($user->id);
    });

    it('belongs to category', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        expect($budget->category->id)->toBe($category->id);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Budget)->getFillable();

        expect($fillable)->toContain(
            'user_id',
            'category_id',
            'amount',
            'period',
            'start_date',
            'is_active',
            'auto_rollover'
        );
    });

    it('casts attributes correctly', function () {
        $budget = Budget::factory()->create();
        $casts = $budget->getCasts();

        expect($casts)
            ->toHaveKey('start_date', 'date')
            ->toHaveKey('is_active', 'boolean')
            ->toHaveKey('auto_rollover', 'boolean');
    });
});

describe('Budget Period Calculations', function () {
    it('calculates monthly period correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => Carbon::create(2024, 3, 15), // Mid-month start
        ]);

        $expectedStart = Carbon::create(2024, 3, 1, 0, 0, 0); // Start of March
        $expectedEnd = Carbon::create(2024, 3, 31, 23, 59, 59); // End of March

        expect($budget->getCurrentPeriodStart()->format('Y-m-d H:i:s'))
            ->toBe($expectedStart->format('Y-m-d H:i:s'));
        expect($budget->getCurrentPeriodEnd()->format('Y-m-d H:i:s'))
            ->toBe($expectedEnd->format('Y-m-d H:i:s'));
    });

    it('calculates weekly period correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        // Start on a Wednesday (2024-03-13)
        $budget = Budget::factory()->weekly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => Carbon::create(2024, 3, 13),
        ]);

        // Week starts on Monday (2024-03-11) and ends on Sunday (2024-03-17)
        $expectedStart = Carbon::create(2024, 3, 11, 0, 0, 0);
        $expectedEnd = Carbon::create(2024, 3, 17, 23, 59, 59);

        expect($budget->getCurrentPeriodStart()->format('Y-m-d H:i:s'))
            ->toBe($expectedStart->format('Y-m-d H:i:s'));
        expect($budget->getCurrentPeriodEnd()->format('Y-m-d H:i:s'))
            ->toBe($expectedEnd->format('Y-m-d H:i:s'));
    });

    it('calculates annual period correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->annual()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => Carbon::create(2024, 6, 15),
        ]);

        $expectedStart = Carbon::create(2024, 1, 1, 0, 0, 0);
        $expectedEnd = Carbon::create(2024, 12, 31, 23, 59, 59);

        expect($budget->getCurrentPeriodStart()->format('Y-m-d H:i:s'))
            ->toBe($expectedStart->format('Y-m-d H:i:s'));
        expect($budget->getCurrentPeriodEnd()->format('Y-m-d H:i:s'))
            ->toBe($expectedEnd->format('Y-m-d H:i:s'));
    });
});

describe('Budget Amount Calculations', function () {
    it('calculates spent amount correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
            'start_date' => now()->startOfMonth(),
        ]);

        // Create transactions within the current period
        Transaction::factory()->expense()->count(3)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        // Create transaction outside the period (should not be counted)
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 50.00,
            'date' => now()->subMonth(),
        ]);

        expect($budget->getSpentAmount())->toBe(300.00);
    });

    it('only counts expense transactions for spent amount', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        // Create expense transactions (should be counted)
        Transaction::factory()->expense()->count(2)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        // Create income transaction (should not be counted)
        Transaction::factory()->income()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 150.00,
            'date' => now(),
        ]);

        expect($budget->getSpentAmount())->toBe(200.00);
    });

    it('only counts transactions for the same user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $category1 = Category::factory()->expense()->create(['user_id' => $user1->id]);
        $category2 = Category::factory()->expense()->create(['user_id' => $user2->id]);
        $account1 = \App\Models\Account::factory()->create(['user_id' => $user1->id]);
        $account2 = \App\Models\Account::factory()->create(['user_id' => $user2->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user1->id,
            'category_id' => $category1->id,
            'amount' => 500.00,
        ]);

        // Create transactions for user1 (should be counted)
        Transaction::factory()->expense()->count(2)->create([
            'user_id' => $user1->id,
            'category_id' => $category1->id,
            'account_id' => $account1->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        // Create transactions for user2 (should not be counted)
        Transaction::factory()->expense()->count(3)->create([
            'user_id' => $user2->id,
            'category_id' => $category2->id,
            'account_id' => $account2->id,
            'amount' => 150.00,
            'date' => now(),
        ]);

        expect($budget->getSpentAmount())->toBe(200.00);
    });

    it('calculates remaining amount correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00,
            'date' => now(),
        ]);

        expect($budget->getRemainingAmount())->toBe(300.00);
    });

    it('returns negative value for overspending', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 500.00,
            'date' => now(),
        ]);

        expect($budget->getRemainingAmount())->toBe(-200.00);
    });
});

describe('Budget Progress and Status', function () {
    it('calculates progress percentage correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00,
            'date' => now(),
        ]);

        expect($budget->getProgressPercentage())->toBe(40);
    });

    it('caps progress percentage at 100', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 500.00,
            'date' => now(),
        ]);

        expect($budget->getProgressPercentage())->toBe(100);
    });

    it('returns zero progress for zero amount budget', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 0.00,
        ]);

        expect($budget->getProgressPercentage())->toBe(0);
    });

    it('returns under status for low spending', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00, // 40%
            'date' => now(),
        ]);

        expect($budget->getStatus())->toBe('under');
    });

    it('returns near status for high spending', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 420.00, // 84%
            'date' => now(),
        ]);

        expect($budget->getStatus())->toBe('near');
    });

    it('returns over status for overspending', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 400.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 500.00, // 125%
            'date' => now(),
        ]);

        expect($budget->getStatus())->toBe('over');
    });

    it('returns correct status colors', function () {
        $user = User::factory()->create();
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        // Test under budget color
        $underCategory = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Under Budget Category',
        ]);
        $underBudget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $underCategory->id,
            'amount' => 500.00,
            'start_date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $underCategory->id,
            'account_id' => $account->id,
            'amount' => 200.00, // 40%
            'date' => now(),
        ]);
        expect($underBudget->getStatusColor())->toBe('success');

        // Test near budget color
        $nearCategory = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Near Budget Category',
        ]);
        $nearBudget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $nearCategory->id,
            'amount' => 400.00,
            'start_date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $nearCategory->id,
            'account_id' => $account->id,
            'amount' => 340.00, // 85%
            'date' => now(),
        ]);
        expect($nearBudget->getStatusColor())->toBe('warning');

        // Test over budget color
        $overCategory = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Over Budget Category',
        ]);
        $overBudget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $overCategory->id,
            'amount' => 300.00,
            'start_date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $overCategory->id,
            'account_id' => $account->id,
            'amount' => 400.00, // 133%
            'date' => now(),
        ]);
        expect($overBudget->getStatus())->toBe('over')
            ->and($overBudget->getStatusColor())->toBe('danger');
    });

    it('returns correct status icons', function () {
        $user = User::factory()->create();
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        // Test under budget icon
        $underCategory = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Under Icon Category',
        ]);
        $underBudget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $underCategory->id,
            'amount' => 500.00,
            'start_date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $underCategory->id,
            'account_id' => $account->id,
            'amount' => 200.00, // 40%
            'date' => now(),
        ]);
        expect($underBudget->getStatusIcon())->toBe('heroicon-o-check-circle');

        // Test near budget icon
        $nearCategory = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Near Icon Category',
        ]);
        $nearBudget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $nearCategory->id,
            'amount' => 400.00,
            'start_date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $nearCategory->id,
            'account_id' => $account->id,
            'amount' => 340.00, // 85%
            'date' => now(),
        ]);
        expect($nearBudget->getStatusIcon())->toBe('heroicon-o-exclamation-circle');

        // Test over budget icon
        $overCategory = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Over Icon Category',
        ]);
        $overBudget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $overCategory->id,
            'amount' => 300.00,
            'start_date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $overCategory->id,
            'account_id' => $account->id,
            'amount' => 400.00, // 133%
            'date' => now(),
        ]);
        expect($overBudget->getStatus())->toBe('over')
            ->and($overBudget->getStatusIcon())->toBe('heroicon-o-exclamation-triangle');
    });
});

describe('Budget Formatting Methods', function () {
    it('formats spent amount correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 123.45,
            'date' => now(),
        ]);

        expect($budget->getFormattedSpent())->toBe('MYR 123.45');
    });

    it('formats budget amount correctly', function () {
        $budget = Budget::factory()->withAmount(567.89)->create();

        expect($budget->getFormattedBudget())->toBe('MYR 567.89');
    });

    it('formats positive remaining amount correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00,
            'date' => now(),
        ]);

        expect($budget->getFormattedRemaining())->toBe('MYR 300.00');
    });

    it('formats negative remaining amount correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 450.00,
            'date' => now(),
        ]);

        expect($budget->getFormattedRemaining())->toBe('-MYR 150.00'); // Shows actual overage amount
    });
});

describe('Budget Status Methods', function () {
    it('correctly identifies over budget', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 400.00,
            'date' => now(),
        ]);

        expect($budget->isOverBudget())->toBeTrue();
        expect($budget->isNearBudget())->toBeFalse();
    });

    it('correctly identifies near budget', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 420.00, // 84%
            'date' => now(),
        ]);

        expect($budget->isOverBudget())->toBeFalse();
        expect($budget->isNearBudget())->toBeTrue();
    });

    it('correctly identifies under budget', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00, // 40%
            'date' => now(),
        ]);

        expect($budget->isOverBudget())->toBeFalse();
        expect($budget->isNearBudget())->toBeFalse();
    });
});

describe('Budget Factory States', function () {
    it('can create monthly budget with factory', function () {
        $budget = Budget::factory()->monthly()->create();

        expect($budget->period)->toBe('monthly');
        expect($budget->start_date->format('d'))->toBe('01'); // Start of month
    });

    it('can create weekly budget with factory', function () {
        $budget = Budget::factory()->weekly()->create();

        expect($budget->period)->toBe('weekly');
        expect($budget->start_date->dayOfWeek)->toBe(1); // Monday
    });

    it('can create annual budget with factory', function () {
        $budget = Budget::factory()->annual()->create();

        expect($budget->period)->toBe('annual');
        expect($budget->start_date->format('m-d'))->toBe('01-01'); // Start of year
    });

    it('can create inactive budget with factory', function () {
        $budget = Budget::factory()->inactive()->create();

        expect($budget->is_active)->toBeFalse();
    });

    it('can create budget with specific amount', function () {
        $budget = Budget::factory()->withAmount(750.50)->create();

        expect($budget->amount)->toBe(750.50);
    });
});
