<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

describe('Transaction Model', function () {
    it('can be created with factory', function () {
        $transaction = Transaction::factory()->create();

        expect($transaction)
            ->toBeInstanceOf(Transaction::class)
            ->and($transaction->type)->toBeIn(['income', 'expense'])
            ->and($transaction->amount)->toBeNumeric()
            ->and($transaction->description)->toBeString();
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        expect($transaction->user->id)->toBe($user->id);
    });

    it('belongs to account', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
        ]);

        expect($transaction->account->id)->toBe($account->id);
    });

    it('belongs to category', function () {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        expect($transaction->category->id)->toBe($category->id);
    });

    it('has from account relationship for transfers', function () {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create(['user_id' => $user->id]);
        $toAccount = Account::factory()->create(['user_id' => $user->id]);

        $transfer = Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'account_id' => $fromAccount->id, // Use fromAccount to satisfy constraint
            'category_id' => null,
        ]);

        expect($transfer->fromAccount->id)->toBe($fromAccount->id);
    });

    it('has to account relationship for transfers', function () {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create(['user_id' => $user->id]);
        $toAccount = Account::factory()->create(['user_id' => $user->id]);

        $transfer = Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'account_id' => $fromAccount->id, // Use fromAccount to satisfy constraint
            'category_id' => null,
        ]);

        expect($transfer->toAccount->id)->toBe($toAccount->id);
    });

    it('updates account balance when created', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'initial_balance' => 1000.00,
            'balance' => 1000.00,
        ]);

        // Mock the updateBalance method to verify it gets called
        $initialBalance = $account->balance;

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 200.00,
        ]);

        // Refresh the account and check balance was updated
        $account->refresh();
        expect($account->balance)->toBeGreaterThan($initialBalance);
    });

    it('returns correct type color attribute', function () {
        $income = Transaction::factory()->income()->make();
        $expense = Transaction::factory()->expense()->make();
        $transfer = Transaction::factory()->transfer()->make();

        expect($income->type_color)->toBe('success');
        expect($expense->type_color)->toBe('danger');
        expect($transfer->type_color)->toBe('info');
    });

    it('returns default color for unknown type', function () {
        $transaction = new Transaction(['type' => 'unknown']);

        expect($transaction->type_color)->toBe('gray');
    });

    it('returns correct type icon attribute', function () {
        $income = Transaction::factory()->income()->make();
        $expense = Transaction::factory()->expense()->make();
        $transfer = Transaction::factory()->transfer()->make();

        expect($income->type_icon)->toBe('heroicon-o-arrow-down-circle');
        expect($expense->type_icon)->toBe('heroicon-o-arrow-up-circle');
        expect($transfer->type_icon)->toBe('heroicon-o-arrow-right-circle');
    });

    it('returns default icon for unknown type', function () {
        $transaction = new Transaction(['type' => 'unknown']);

        expect($transaction->type_icon)->toBe('heroicon-o-circle-stack');
    });

    it('formats amount correctly for income', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'currency' => 'MYR',
        ]);

        $income = Transaction::factory()->income()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 1234.56,
        ]);

        expect($income->formatted_amount)->toBe('+MYR 1,234.56');
    });

    it('formats amount correctly for expense', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
        ]);

        $expense = Transaction::factory()->expense()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 567.89,
        ]);

        expect($expense->formatted_amount)->toBe('-USD 567.89');
    });

    it('formats amount correctly for transfer', function () {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create([
            'user_id' => $user->id,
            'currency' => 'EUR',
        ]);

        $transfer = Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'account_id' => $fromAccount->id,
            'category_id' => null,
            'amount' => 300.00,
        ]);

        // For transfers, the fromAccount's currency should be used
        expect($transfer->formatted_amount)->toBe('EUR 300.00');
    });

    it('uses default currency when account is null', function () {
        $transaction = Transaction::factory()->make([
            'account_id' => null,
            'type' => 'income',
            'amount' => 100.00,
        ]);

        expect($transaction->formatted_amount)->toBe('+USD 100.00');
    });

    it('can be created with different factory states', function () {
        $income = Transaction::factory()->income()->make();
        $expense = Transaction::factory()->expense()->make();
        $transfer = Transaction::factory()->transfer()->make();

        expect($income->type)->toBe('income');
        expect($expense->type)->toBe('expense');
        expect($transfer->type)->toBe('transfer');
    });

    it('can be created with specific attributes', function () {
        $transaction = Transaction::factory()
            ->withAmount(500.00)
            ->withDate('2023-06-15')
            ->reconciled()
            ->make();

        expect((float) $transaction->amount)->toBe(500.0);
        expect($transaction->date->format('Y-m-d'))->toBe('2023-06-15');
        expect($transaction->is_reconciled)->toBeTrue();
    });

    it('can be created for specific months', function () {
        $thisMonth = Transaction::factory()->thisMonth()->make();
        $lastMonth = Transaction::factory()->lastMonth()->make();

        expect($thisMonth->date->month)->toBe(now()->month);
        expect($thisMonth->date->year)->toBe(now()->year);

        expect($lastMonth->date->month)->toBe(now()->subMonth()->month);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Transaction)->getFillable();

        expect($fillable)->toContain(
            'user_id',
            'type',
            'amount',
            'description',
            'date',
            'account_id',
            'category_id',
            'from_account_id',
            'to_account_id',
            'reference',
            'notes',
            'is_reconciled'
        );
    });

    it('casts attributes correctly', function () {
        $transaction = Transaction::factory()->create();
        $casts = $transaction->getCasts();

        expect($casts)
            ->toHaveKey('date', 'date')
            ->toHaveKey('is_reconciled', 'boolean');

        // Amount is now handled via accessor/mutator, not cast
        // Verify the accessor works by checking the value
        expect($transaction->amount)->toBeNumeric();
    });

    it('updates both account balances for transfers', function () {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create([
            'user_id' => $user->id,
            'initial_balance' => 1000.00,
            'balance' => 1000.00,
        ]);
        $toAccount = Account::factory()->create([
            'user_id' => $user->id,
            'initial_balance' => 500.00,
            'balance' => 500.00,
        ]);

        $initialFromBalance = $fromAccount->balance;
        $initialToBalance = $toAccount->balance;

        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'account_id' => $fromAccount->id, // Use fromAccount to satisfy constraint
            'category_id' => null,
            'amount' => 200.00,
        ]);

        $fromAccount->refresh();
        $toAccount->refresh();

        expect($fromAccount->balance)->toBeLessThan($initialFromBalance);
        expect($toAccount->balance)->toBeGreaterThan($initialToBalance);
    });
});
