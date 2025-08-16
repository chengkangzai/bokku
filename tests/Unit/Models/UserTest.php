<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

describe('User Model', function () {
    it('can be created with factory', function () {
        $user = User::factory()->create();

        expect($user)
            ->toBeInstanceOf(User::class)
            ->and($user->name)->toBeString()
            ->and($user->email)->toContain('@')
            ->and($user->password)->toBeString();
    });

    it('has accounts relationship', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        expect($user->accounts)
            ->toHaveCount(1)
            ->and($user->accounts->first()->id)->toBe($account->id);
    });

    it('has categories relationship', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        expect($user->categories)
            ->toHaveCount(1)
            ->and($user->categories->first()->id)->toBe($category->id);
    });

    it('has transactions relationship', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        expect($user->transactions)
            ->toHaveCount(1)
            ->and($user->transactions->first()->id)->toBe($transaction->id);
    });

    it('can access filament panel', function () {
        $user = User::factory()->create();
        $panel = new \Filament\Panel('admin');

        expect($user->canAccessPanel($panel))->toBeTrue();
    });

    it('calculates net worth correctly', function () {
        $user = User::factory()->create();

        // Assets
        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'bank',
            'balance' => 1000.50,
        ]);

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'cash',
            'balance' => 2500.75,
        ]);

        // Liabilities (stored as positive values)
        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'credit_card',
            'balance' => 500.25, // Credit card debt stored as positive
        ]);

        // Net Worth = Assets - Liabilities = (1000.50 + 2500.75) - 500.25 = 3001
        expect($user->net_worth)->toBe(3001.0);
    });

    it('returns zero net worth when no accounts', function () {
        $user = User::factory()->create();

        expect($user->net_worth)->toBe(0.0);
    });

    it('only includes own accounts in net worth calculation', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user1->id,
            'type' => 'bank',
            'balance' => 1000.00,
        ]);

        Account::factory()->create([
            'user_id' => $user2->id,
            'type' => 'bank',
            'balance' => 5000.00,
        ]);

        expect($user1->net_worth)->toBe(1000.0);
        expect($user2->net_worth)->toBe(5000.0);
    });

    it('calculates net worth correctly with multiple liability types', function () {
        $user = User::factory()->create();

        // Assets
        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'bank',
            'balance' => 5000.00,
        ]);

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'cash',
            'balance' => 1000.00,
        ]);

        // Liabilities (stored as positive values)
        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'credit_card',
            'balance' => 1500.00, // Credit card debt
        ]);

        Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'loan',
            'balance' => 2000.00, // Loan debt
        ]);

        // Net Worth = Assets - Liabilities = (5000 + 1000) - (1500 + 2000) = 2500
        expect($user->net_worth)->toBe(2500.0);
    });

    it('has required fillable attributes', function () {
        $fillable = (new User)->getFillable();

        expect($fillable)->toContain('name', 'email', 'password');
    });

    it('has hidden attributes for security', function () {
        $user = User::factory()->create();
        $hidden = $user->getHidden();

        expect($hidden)->toContain('password', 'remember_token');
    });

    it('casts attributes correctly', function () {
        $user = User::factory()->create();
        $casts = $user->getCasts();

        expect($casts)
            ->toHaveKey('email_verified_at', 'datetime')
            ->toHaveKey('password', 'hashed');
    });
});
