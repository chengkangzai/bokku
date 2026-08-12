<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Filament\Panel;

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
        $panel = (new Panel)->id('admin');

        expect($user->canAccessPanel($panel))->toBeTrue();
    });

    it('non-admin cannot access superadmin panel', function () {
        $user = User::factory()->create(['is_admin' => false]);
        $panel = (new Panel)->id('superadmin');

        expect($user->canAccessPanel($panel))->toBeFalse();
    });

    it('admin can access superadmin panel', function () {
        $user = User::factory()->create(['is_admin' => true]);
        $panel = (new Panel)->id('superadmin');

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

describe('User Asset and Liability Totals', function () {
    it('splits assets from liabilities by account type', function () {
        $user = User::factory()->create();

        Account::factory()->bank()->create(['user_id' => $user->id, 'balance' => 1000.00]);
        Account::factory()->cash()->create(['user_id' => $user->id, 'balance' => 250.00]);
        Account::factory()->creditCard()->create(['user_id' => $user->id, 'balance' => 300.00]);
        Account::factory()->loan()->create(['user_id' => $user->id, 'balance' => 5000.00]);

        expect($user->total_assets)->toBe(1250.0)
            ->and($user->total_liabilities)->toBe(5300.0)
            ->and($user->net_worth)->toBe(-4050.0);
    });

    it('includes inactive accounts in the totals', function () {
        $user = User::factory()->create();

        Account::factory()->bank()->inactive()->create(['user_id' => $user->id, 'balance' => 800.00]);

        expect($user->total_assets)->toBe(800.0);
    });

    it('returns zero totals when the user has no accounts', function () {
        $user = User::factory()->create();

        expect($user->total_assets)->toBe(0.0)
            ->and($user->total_liabilities)->toBe(0.0);
    });
});

describe('User Net Worth History', function () {
    it('returns one bucket per month, oldest first', function () {
        $user = User::factory()->create();

        $history = $user->netWorthHistory();

        expect($history)->toHaveCount(12)
            ->and(array_key_first($history))->toBe(now()->subMonths(11)->format('Y-m'))
            ->and(array_key_last($history))->toBe(now()->format('Y-m'));
    });

    it('respects the requested number of months', function () {
        $user = User::factory()->create();

        expect($user->netWorthHistory(6))->toHaveCount(6);
    });

    it('ends on the current net worth', function () {
        $user = User::factory()->create();

        $account = Account::factory()->bank()->create([
            'user_id' => $user->id,
            'initial_balance' => 1000.00,
            'balance' => 1000.00,
        ]);

        Transaction::factory()->expense()->withAmount(150.00)->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'date' => now(),
        ]);

        $history = $user->netWorthHistory();

        expect(end($history))->toBe($user->refresh()->net_worth);
    });

    it('reflects a transaction only from the month it occurred', function () {
        $user = User::factory()->create();

        $account = Account::factory()->bank()->create([
            'user_id' => $user->id,
            'initial_balance' => 1000.00,
            'balance' => 1000.00,
        ]);

        Transaction::factory()->expense()->withAmount(200.00)->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'date' => now()->startOfMonth(),
        ]);

        $history = $user->netWorthHistory();

        expect($history[now()->subMonth()->format('Y-m')])->toBe(1000.0)
            ->and($history[now()->format('Y-m')])->toBe(800.0);
    });

    it('increases liability outstanding when an expense is charged to it', function () {
        $user = User::factory()->create();

        $loan = Account::factory()->loan()->create([
            'user_id' => $user->id,
            'initial_balance' => 5000.00,
            'balance' => 5000.00,
        ]);

        Transaction::factory()->expense()->withAmount(500.00)->create([
            'user_id' => $user->id,
            'account_id' => $loan->id,
            'date' => now()->startOfMonth(),
        ]);

        $history = $user->netWorthHistory();

        expect($loan->refresh()->balance)->toBe(5500.0)
            ->and($history[now()->subMonth()->format('Y-m')])->toBe(-5000.0)
            ->and($history[now()->format('Y-m')])->toBe(-5500.0)
            ->and(end($history))->toBe($user->refresh()->net_worth);
    });

    it('accounts for transfers between an asset and a liability', function () {
        $user = User::factory()->create();

        $bank = Account::factory()->bank()->create([
            'user_id' => $user->id,
            'initial_balance' => 3000.00,
            'balance' => 3000.00,
        ]);

        $loan = Account::factory()->loan()->create([
            'user_id' => $user->id,
            'initial_balance' => 2000.00,
            'balance' => 2000.00,
        ]);

        Transaction::factory()->transfer()->withAmount(500.00)->create([
            'user_id' => $user->id,
            'account_id' => $bank->id,
            'category_id' => null,
            'from_account_id' => $bank->id,
            'to_account_id' => $loan->id,
            'date' => now()->startOfMonth(),
        ]);

        $history = $user->netWorthHistory();

        expect($bank->refresh()->balance)->toBe(2500.0)
            ->and($loan->refresh()->balance)->toBe(1500.0)
            ->and($history[now()->subMonth()->format('Y-m')])->toBe(1000.0)
            ->and($history[now()->format('Y-m')])->toBe(1000.0)
            ->and(end($history))->toBe($user->refresh()->net_worth);
    });

    it('only includes the user own accounts and transactions', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Account::factory()->bank()->create([
            'user_id' => $user->id,
            'initial_balance' => 100.00,
            'balance' => 100.00,
        ]);

        $otherAccount = Account::factory()->bank()->create([
            'user_id' => $other->id,
            'initial_balance' => 9999.00,
            'balance' => 9999.00,
        ]);

        Transaction::factory()->expense()->withAmount(50.00)->create([
            'user_id' => $other->id,
            'account_id' => $otherAccount->id,
            'date' => now(),
        ]);

        $history = $user->netWorthHistory();

        expect(end($history))->toBe(100.0)
            ->and($history)->toHaveCount(12);
    });
});
