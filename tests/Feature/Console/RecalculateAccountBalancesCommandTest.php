<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

it('recalculates stale account balances from transactions', function () {
    $user = User::factory()->create();

    $bank = Account::factory()->bank()->create([
        'user_id' => $user->id,
        'initial_balance' => 1000.00,
        'balance' => 1000.00,
    ]);
    $loan = Account::factory()->loan()->create([
        'user_id' => $user->id,
        'initial_balance' => 5000.00,
        'balance' => 5000.00,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => 'transfer',
        'amount' => 200.00,
        'from_account_id' => $bank->id,
        'to_account_id' => $loan->id,
        'account_id' => $bank->id,
        'category_id' => null,
    ]);

    Account::whereKey($loan->id)->update(['balance' => 999999]);

    $this->artisan('accounts:recalculate-balances')
        ->expectsOutputToContain('Recalculated 2 accounts.')
        ->assertSuccessful();

    expect((float) $bank->refresh()->balance)->toBe(800.0)
        ->and((float) $loan->refresh()->balance)->toBe(4800.0);
});
