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

it('reconciles opening cash through flows to closing cash', function () {
    $bank = Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 1000.00,
        'balance' => 1000.00,
    ]);
    $loan = Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 10000.00,
        'balance' => 10000.00,
    ]);
    $card = Account::factory()->creditCard()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 0.00,
        'balance' => 0.00,
    ]);
    $mum = Account::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'cash',
        'initial_balance' => 0.00,
        'balance' => 0.00,
        'exclude_from_net_worth' => true,
    ]);

    Transaction::factory()->income()->withAmount(5000.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(1200.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(300.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $card->id, // card charge: an expense, but not a cash movement
        'date' => now()->startOfMonth(),
    ]);

    foreach ([[$loan, 700.00], [$card, 250.00], [$mum, 400.00]] as [$target, $amount]) {
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

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'transfer',
        'amount' => 150.00,
        'from_account_id' => $mum->id,
        'to_account_id' => $bank->id,
        'account_id' => $mum->id,
        'category_id' => null,
        'date' => now()->startOfMonth(),
    ]);

    $cash = (new SpendingAnalysisService)->cashReconciliation($this->user->id, CarbonImmutable::now(), 2);

    expect(end($cash['income']))->toBe(5000.0)
        ->and(end($cash['expenses']))->toBe(-1200.0) // card charge not cash
        ->and(end($cash['loan_payments']))->toBe(-700.0)
        ->and(end($cash['card_payments']))->toBe(-250.0)
        ->and(end($cash['relay']))->toBe(-250.0) // 400 out, 150 back
        ->and(end($cash['closing']))->toBe(1000.0 + 5000.0 - 1200.0 - 700.0 - 250.0 - 250.0)
        ->and(end($cash['closing']))->toBe((float) $bank->refresh()->balance);
});

it('shows the reconciliation on the page', function () {
    Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 2500.00,
        'balance' => 2500.00,
    ]);

    livewire(CashflowStatement::class)
        ->assertSee('Opening Cash')
        ->assertSee('Closing Cash')
        ->assertSee('2,500.00');
});
