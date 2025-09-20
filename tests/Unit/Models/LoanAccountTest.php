<?php

use App\Models\Account;

describe('Loan Account Model', function () {
    it('correctly formats loan balance as positive outstanding amount', function () {
        $loan = Account::factory()->loan()->create([
            'balance' => 45000, // Now positive value representing amount owed
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

    it('identifies liability accounts correctly', function () {
        $loan = Account::factory()->loan()->create();
        $creditCard = Account::factory()->creditCard()->create();
        $bank = Account::factory()->bank()->create();

        expect($loan->isLiability())->toBeTrue();
        expect($creditCard->isLiability())->toBeTrue();
        expect($bank->isLiability())->toBeFalse();
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
            'initial_balance' => 60000, // Positive value representing initial amount owed
            'balance' => 60000,
        ]);

        // Simulate a payment (reduces the outstanding amount)
        $loan->balance = 58800; // After 1200 payment
        $loan->save();

        expect($loan->formatted_balance)->toBe($loan->currency.' 58,800.00');
    });

    it('shows correct icon for loan accounts from enum', function () {
        $loan = Account::factory()->loan()->create();

        expect($loan->type->getIcon())->toBe('heroicon-o-document-text');
    });

    it('correctly formats credit card balance as positive outstanding amount', function () {
        $creditCard = Account::factory()->creditCard()->create([
            'balance' => 2500, // Now positive value representing amount owed
            'currency' => 'MYR',
        ]);

        expect($creditCard->formatted_balance)->toBe('MYR 2,500.00');
        expect($creditCard->balance_label)->toBe('Outstanding');
        expect($creditCard->isLiability())->toBeTrue();
        expect($creditCard->isLoan())->toBeFalse();
    });
});
