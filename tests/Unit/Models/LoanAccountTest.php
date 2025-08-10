<?php

use App\Models\Account;

describe('Loan Account Model', function () {
    it('correctly formats loan balance as positive outstanding amount', function () {
        $loan = Account::factory()->loan()->create([
            'balance' => -45000,
            'currency' => 'MYR',
        ]);

        expect($loan->formatted_balance)->toBe('MYR 45,000.00');
        expect($loan->balance_label)->toBe('Outstanding');
    });

    it('identifies loan accounts correctly', function () {
        $loan = Account::factory()->loan()->create();
        $bank = Account::factory()->bank()->create();

        expect($loan->isLoan())->toBeTrue();
        expect($bank->isLoan())->toBeFalse();
    });

    it('formats regular account balance normally', function () {
        $bank = Account::factory()->bank()->create([
            'balance' => 5000,
            'currency' => 'MYR',
        ]);

        expect($bank->formatted_balance)->toBe('MYR 5,000.00');
        expect($bank->balance_label)->toBe('Balance');
    });

    it('reduces loan outstanding when payment is made', function () {
        $loan = Account::factory()->loan()->create([
            'initial_balance' => -60000,
            'balance' => -60000,
        ]);

        // Simulate a payment (transfer in)
        $loan->balance = -58800; // After 1200 payment
        $loan->save();

        expect($loan->formatted_balance)->toBe($loan->currency.' 58,800.00');
    });

    it('shows correct icon for loan accounts', function () {
        $loan = Account::factory()->loan()->create();

        expect($loan->getTypeIconAttribute())->toBe('heroicon-o-document-text');
    });
});
