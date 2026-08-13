<?php

use App\Filament\Pages\CashflowStatement;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingAnalysisService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render the cashflow page in the Insights group', function () {
    livewire(CashflowStatement::class)->assertSuccessful();

    $reflectionClass = new ReflectionClass(CashflowStatement::class);
    expect($reflectionClass->getProperty('navigationGroup')->getValue())->toBe('Insights');
});

it('computes free cashflow as net income minus own loan repayments', function () {
    $bank = Account::factory()->bank()->create(['user_id' => $this->user->id]);
    $ownLoan = Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 10000.00,
        'balance' => 10000.00,
    ]);
    $mumLoan = Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 5000.00,
        'balance' => 5000.00,
        'exclude_from_net_worth' => true,
    ]);
    $creditCard = Account::factory()->creditCard()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 500.00,
        'balance' => 500.00,
    ]);

    Transaction::factory()->income()->withAmount(5000.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(2000.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->startOfMonth(),
    ]);

    foreach ([[$ownLoan, 700.00], [$mumLoan, 300.00], [$creditCard, 500.00]] as [$target, $amount]) {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'transfer',
            'amount' => $amount,
            'from_account_id' => $bank->id,
            'to_account_id' => $target->id,
            'account_id' => $bank->id,
            'category_id' => null,
            'date' => now()->startOfMonth(),
        ]);
    }

    $cashflow = (new SpendingAnalysisService)->cashflow($this->user->id, CarbonImmutable::now(), 2);

    expect(end($cashflow['net']))->toBe(3000.0)
        ->and(end($cashflow['debt_total']))->toBe(700.0) // own loan only: no mum loan, no credit card
        ->and(end($cashflow['free']))->toBe(2300.0)
        ->and($cashflow['debt_service'])->toHaveCount(1)
        ->and($cashflow['debt_service'][0]['name'])->toBe($ownLoan->name);
});

it('shows the figures on the page', function () {
    $bank = Account::factory()->bank()->create(['user_id' => $this->user->id]);
    $loan = Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 10000.00,
        'balance' => 10000.00,
    ]);

    Transaction::factory()->income()->withAmount(4000.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'transfer',
        'amount' => 703.00,
        'from_account_id' => $bank->id,
        'to_account_id' => $loan->id,
        'account_id' => $bank->id,
        'category_id' => null,
        'date' => now()->startOfMonth(),
    ]);

    livewire(CashflowStatement::class)
        ->assertSee($loan->name)
        ->assertSee('703.00')
        ->assertSee('+3,297.00'); // 4,000 - 0 expenses - 703
});
