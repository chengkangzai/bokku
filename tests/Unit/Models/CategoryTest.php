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

        $specificDate = now()->setMonth(6)->setYear(2023);

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

        expect($incomeCategory->getDefaultIconAttribute())->toBe('heroicon-o-arrow-trending-up');
    });

    it('returns correct default icon for expense category', function () {
        $expenseCategory = Category::factory()->expense()->create();

        expect($expenseCategory->getDefaultIconAttribute())->toBe('heroicon-o-arrow-trending-down');
    });

    it('returns default icon for unknown type', function () {
        $category = Category::factory()->make(['type' => 'unknown']);

        expect($category->getDefaultIconAttribute())->toBe('heroicon-o-tag');
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

        expect($incomeNames)->toContain($incomeCategory->name);
        expect($expenseNames)->toContain($expenseCategory->name);
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
});
