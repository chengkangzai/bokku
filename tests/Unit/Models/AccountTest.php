<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

describe('Account Model', function () {
    it('can be created with factory', function () {
        $account = Account::factory()->create();

        expect($account)
            ->toBeInstanceOf(Account::class)
            ->and($account->name)->toBeString()
            ->and($account->type)->toBeIn(['bank', 'cash', 'credit_card', 'loan'])
            ->and($account->currency)->toBeString()
            ->and($account->is_active)->toBeBool();
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        expect($account->user->id)->toBe($user->id);
    });

    it('has many transactions', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
        ]);

        expect($account->transactions)
            ->toHaveCount(1)
            ->and($account->transactions->first()->id)->toBe($transaction->id);
    });

    it('has transfers from relationship', function () {
        $user = User::factory()->create();
        $fromAccount = Account::factory()->create(['user_id' => $user->id]);
        $toAccount = Account::factory()->create(['user_id' => $user->id]);

        // Create a transfer with a dummy account_id to satisfy NOT NULL constraint
        // but use from/to accounts for the actual transfer logic
        $transfer = Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'account_id' => $fromAccount->id, // Use fromAccount to satisfy constraint
            'category_id' => null,
        ]);

        expect($fromAccount->transfersFrom)
            ->toHaveCount(1)
            ->and($fromAccount->transfersFrom->first()->id)->toBe($transfer->id);
    });

    it('has transfers to relationship', function () {
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

        expect($toAccount->transfersTo)
            ->toHaveCount(1)
            ->and($toAccount->transfersTo->first()->id)->toBe($transfer->id);
    });

    it('updates balance correctly with income and expenses', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'initial_balance' => 1000.00,
            'balance' => 1000.00,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'income',
            'amount' => 500.00,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 200.00,
        ]);

        $account->updateBalance();

        expect((float) $account->balance)->toBe(1300.0); // 1000 + 500 - 200
    });

    it('updates balance correctly with transfers', function () {
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

        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'amount' => 200.00,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'account_id' => $fromAccount->id, // Use fromAccount to satisfy constraint
            'category_id' => null,
        ]);

        $fromAccount->updateBalance();
        $toAccount->updateBalance();

        expect((float) $fromAccount->balance)->toBe(800.0); // 1000 - 200 (transfer out)
        expect((float) $toAccount->balance)->toBe(700.0);   // 500 + 200 (transfer in)
    });

    it('returns correct type icon', function () {
        $bankAccount = Account::factory()->bank()->create();
        $cashAccount = Account::factory()->cash()->create();
        $creditCard = Account::factory()->creditCard()->create();
        $loan = Account::factory()->loan()->create();

        expect($bankAccount->getTypeIconAttribute())->toBe('heroicon-o-building-library');
        expect($cashAccount->getTypeIconAttribute())->toBe('heroicon-o-banknotes');
        expect($creditCard->getTypeIconAttribute())->toBe('heroicon-o-credit-card');
        expect($loan->getTypeIconAttribute())->toBe('heroicon-o-document-text');
    });

    it('returns default icon for unknown type', function () {
        $account = Account::factory()->make(['type' => 'unknown']);

        expect($account->getTypeIconAttribute())->toBe('heroicon-o-wallet');
    });

    it('formats balance correctly', function () {
        $account = Account::factory()->create([
            'balance' => 1234.56,
            'currency' => 'MYR',
        ]);

        expect($account->getFormattedBalanceAttribute())->toBe('MYR 1,234.56');
    });

    it('handles different account types from factory', function () {
        $bank = Account::factory()->bank()->create();
        $cash = Account::factory()->cash()->create();
        $creditCard = Account::factory()->creditCard()->create();
        $loan = Account::factory()->loan()->create();

        expect($bank->type)->toBe('bank');
        expect($cash->type)->toBe('cash');
        expect($cash->account_number)->toBeNull();
        expect($creditCard->type)->toBe('credit_card');
        expect($creditCard->balance)->toBeLessThanOrEqual(0);
        expect($loan->type)->toBe('loan');
        expect($loan->balance)->toBeLessThan(0);
    });

    it('has correct fillable attributes', function () {
        $fillable = (new Account)->getFillable();

        expect($fillable)->toContain(
            'user_id',
            'name',
            'type',
            'icon',
            'color',
            'balance',
            'initial_balance',
            'currency',
            'account_number',
            'notes',
            'is_active'
        );
    });

    it('casts attributes correctly', function () {
        $account = Account::factory()->create();
        $casts = $account->getCasts();

        expect($casts)
            ->toHaveKey('balance', 'decimal:2')
            ->toHaveKey('initial_balance', 'decimal:2')
            ->toHaveKey('is_active', 'boolean');
    });

    it('can be created with different currencies', function () {
        $account = Account::factory()->withCurrency('USD')->create();

        expect($account->currency)->toBe('USD');
    });

    it('can be set as active or inactive', function () {
        $activeAccount = Account::factory()->active()->create();
        $inactiveAccount = Account::factory()->inactive()->create();

        expect($activeAccount->is_active)->toBeTrue();
        expect($inactiveAccount->is_active)->toBeFalse();
    });
});
