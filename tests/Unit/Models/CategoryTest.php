<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

describe('Category Model', function () {
    it('can be created with factory', function () {
        $category = Category::factory()->create();

        expect($category)
            ->toBeInstanceOf(Category::class)
            ->and($category->name)->toBeString()
            ->and($category->type)->toBeIn(['income', 'expense'])
            ->and($category->sort_order)->toBeInt();
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        expect($category->user->id)->toBe($user->id);
    });

    it('has many transactions', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        expect($category->transactions)
            ->toHaveCount(1)
            ->and($category->transactions->first()->id)->toBe($transaction->id);
    });

    it('calculates monthly total correctly for current month', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(2)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 50.00,
            'date' => now()->subMonth(), // Previous month, should not be included
        ]);

        expect($category->getMonthlyTotal())->toBe(200.0);
    });

    it('calculates monthly total for specific month and year', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $specificDate = \Carbon\Carbon::create(2023, 6, 1);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 150.00,
            'date' => $specificDate,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 75.00,
            'date' => $specificDate->copy()->addDays(5),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'date' => now(), // Current month, should not be included
        ]);

        expect($category->getMonthlyTotal(6, 2023))->toBe(225.0);
    });

    it('returns zero monthly total when no transactions', function () {
        $category = Category::factory()->create();

        expect($category->getMonthlyTotal())->toBe(0.0);
    });

    it('returns correct default icon for income category', function () {
        $incomeCategory = Category::factory()->income()->create();

        expect($incomeCategory->default_icon)->toBe('heroicon-o-arrow-trending-up');
    });

    it('returns correct default icon for expense category', function () {
        $expenseCategory = Category::factory()->expense()->create();

        expect($expenseCategory->default_icon)->toBe('heroicon-o-arrow-trending-down');
    });

    it('returns default icon for unknown type', function () {
        $category = Category::factory()->make(['type' => 'unknown']);

        expect($category->default_icon)->toBe('heroicon-o-tag');
    });

    it('can be created with specific type from factory', function () {
        $incomeCategory = Category::factory()->income()->create();
        $expenseCategory = Category::factory()->expense()->create();

        expect($incomeCategory->type)->toBe('income');
        expect($expenseCategory->type)->toBe('expense');
    });

    it('can be created with specific sort order', function () {
        $category = Category::factory()->withSortOrder(5)->create();

        expect($category->sort_order)->toBe(5);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Category)->getFillable();

        expect($fillable)->toContain(
            'user_id',
            'name',
            'type',
            'icon',
            'color',
            'sort_order'
        );
    });

    it('casts attributes correctly', function () {
        $category = Category::factory()->create();
        $casts = $category->getCasts();

        expect($casts)->toHaveKey('sort_order', 'integer');
    });

    it('creates appropriate category names based on type', function () {
        $incomeCategory = Category::factory()->income()->create();
        $expenseCategory = Category::factory()->expense()->create();

        $incomeNames = [
            'Salary', 'Freelance', 'Investment', 'Business', 'Rental Income',
            'Bonus', 'Commission', 'Interest', 'Dividend', 'Gift',
        ];

        $expenseNames = [
            'Food & Dining', 'Transportation', 'Shopping', 'Entertainment',
            'Utilities', 'Healthcare', 'Education', 'Travel', 'Insurance',
            'Groceries', 'Rent', 'Internet', 'Phone', 'Fuel', 'Clothing',
        ];

        // Factory adds unique suffix, so we need to check the base name
        $incomeCategoryBaseName = explode('_', $incomeCategory->name)[0];
        $expenseCategoryBaseName = explode('_', $expenseCategory->name)[0];

        expect($incomeNames)->toContain($incomeCategoryBaseName);
        expect($expenseNames)->toContain($expenseCategoryBaseName);
    });

    it('calculates monthly total only for transactions in the same category', function () {
        $user = User::factory()->create();
        $category1 = Category::factory()->create(['user_id' => $user->id]);
        $category2 = Category::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category1->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category2->id,
            'amount' => 200.00,
            'date' => now(),
        ]);

        expect($category1->getMonthlyTotal())->toBe(100.0);
        expect($category2->getMonthlyTotal())->toBe(200.0);
    });

    it('has many budgets relationship', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $budget = \App\Models\Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        expect($category->budgets)
            ->toHaveCount(1)
            ->and($category->budgets->first()->id)->toBe($budget->id);
    });
});

describe('Category Budget Integration', function () {
    it('can get active budget for user', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $activeBudget = \App\Models\Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $result = $category->getBudgetForUser($user->id);
        expect($result->id)->toBe($activeBudget->id);
    });

    it('returns null when no active budget exists', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        expect($category->getBudgetForUser($user->id))->toBeNull();
    });

    it('only returns budget for specified user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user1->id]);

        \App\Models\Budget::factory()->create([
            'user_id' => $user2->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        expect($category->getBudgetForUser($user1->id))->toBeNull();
    });

    it('correctly identifies if category has budget', function () {
        $user = User::factory()->create();
        $categoryWithBudget = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Category With Budget',
        ]);
        $categoryWithoutBudget = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Category Without Budget',
        ]);

        \App\Models\Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $categoryWithBudget->id,
            'is_active' => true,
        ]);

        expect($categoryWithBudget->hasBudget())->toBeTrue();
        expect($categoryWithoutBudget->hasBudget())->toBeFalse();
    });

    it('returns correct budget status', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Budget Status Category',
        ]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        $budget = \App\Models\Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
            'is_active' => true,
            'start_date' => now()->startOfMonth(),
        ]);

        // Create transaction to make it "near" budget (84%)
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 420.00, // 84% of 500
            'date' => now(),
        ]);

        expect($category->getBudgetProgress())->toBe(84)
            ->and($category->getBudgetStatus())->toBe('near');
    });

    it('returns null budget status when no budget exists', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        expect($category->getBudgetStatus())->toBeNull();
    });

    it('returns correct budget progress', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Budget Progress Category',
        ]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
            'is_active' => true,
            'start_date' => now()->startOfMonth(),
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00, // 40%
            'date' => now(),
        ]);

        expect($category->getBudgetProgress())->toBe(40);
    });

    it('returns zero progress when no budget exists', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        expect($category->getBudgetProgress())->toBe(0);
    });
});

describe('Category Budget Warnings', function () {
    it('returns warning when transaction will exceed budget', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Groceries',
        ]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
            'is_active' => true,
            'start_date' => now()->startOfMonth(),
        ]);

        // Current spending: 300
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 300.00,
            'date' => now(),
        ]);

        // Additional transaction of 250 would make total 550, exceeding 500 budget
        $warning = $category->getBudgetWarning(250.00);

        expect($warning)->toBeString()
            ->and($warning)->toContain('⚠️ This will put you MYR 50.00 over your Groceries budget');
    });

    it('returns percentage warning when approaching budget limit', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Entertainment',
        ]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 400.00,
            'is_active' => true,
            'start_date' => now()->startOfMonth(),
        ]);

        // Current spending: 200
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 200.00,
            'date' => now(),
        ]);

        // Additional transaction of 140 would make total 340 (85% of 400)
        $warning = $category->getBudgetWarning(140.00);

        expect($warning)->toBeString()
            ->and($warning)->toContain('💡 This will use 85% of your Entertainment budget');
    });

    it('returns null when transaction is well within budget', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
            'is_active' => true,
        ]);

        // Current spending: 100
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        // Additional transaction of 50 would make total 150 (30% of 500)
        $warning = $category->getBudgetWarning(50.00);

        expect($warning)->toBeNull();
    });

    it('returns null when no budget exists', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create(['user_id' => $user->id]);

        $warning = $category->getBudgetWarning(100.00);

        expect($warning)->toBeNull();
    });

    it('handles zero additional amount correctly', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Zero Amount Category',
        ]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 400.00,
            'is_active' => true,
            'start_date' => now()->startOfMonth(),
        ]);

        // Current spending: 350 (87.5% of budget)
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 350.00,
            'date' => now(),
        ]);

        // Zero additional amount should still show current percentage
        $warning = $category->getBudgetWarning(0.00);

        expect($warning)->toBeString()
            ->and($warning)->toContain('💡 This will use 88% of your Zero Amount Category budget');
    });

    it('calculates warning based on current period spending only', function () {
        $user = User::factory()->create();
        $category = Category::factory()->expense()->create([
            'user_id' => $user->id,
            'name' => 'Food',
        ]);
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        \App\Models\Budget::factory()->monthly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
            'is_active' => true,
            'start_date' => now()->startOfMonth(),
        ]);

        // Previous month spending (should not affect current budget warning)
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 500.00,
            'date' => now()->subMonth(),
        ]);

        // Current month spending: 100
        Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        // Additional 50 would make current month total 150, not exceeding 300 budget
        $warning = $category->getBudgetWarning(50.00);

        expect($warning)->toBeNull(); // Should be well within budget
    });
});
