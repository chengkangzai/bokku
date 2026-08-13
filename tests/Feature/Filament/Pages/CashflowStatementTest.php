<?php

use App\Filament\Pages\CashflowStatement;
use App\Models\Account;
use App\Models\Category;
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

it('builds a cash-basis statement with category and transfer rows', function () {
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

    $salary = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'income', 'name' => 'Salary']);
    $food = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Food']);

    Transaction::factory()->income()->withAmount(5000.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'category_id' => $salary->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(1200.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'category_id' => $food->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(300.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $card->id, // card charge: an expense on the P&L, not a cash movement
        'category_id' => $food->id,
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

    $cash = (new SpendingAnalysisService)->cashStatement($this->user->id, CarbonImmutable::now(), 2);

    $inNames = array_column($cash['cash_in'], 'name');
    $outNames = array_column($cash['cash_out'], 'name');
    $outByName = collect($cash['cash_out'])->keyBy('name');

    expect($inNames)->toContain('Salary')
        ->and($inNames)->toContain('Family / relay in')
        ->and(end($cash['in_total']))->toBe(5150.0) // 5,000 salary + 150 relay in
        ->and($outNames)->toContain('Food')
        ->and(end($outByName->get('Food')['values']))->toBe(1200.0) // card charge excluded
        ->and(end($outByName->get($loan->name.' — repayment')['values']))->toBe(700.0)
        ->and(end($outByName->get($card->name.' — bill payment')['values']))->toBe(250.0)
        ->and(end($outByName->get('Family / relay out')['values']))->toBe(400.0)
        ->and(end($cash['out_total']))->toBe(2550.0)
        ->and(end($cash['net']))->toBe(2600.0)
        ->and(end($cash['closing']))->toBe(3600.0)
        ->and(end($cash['closing']))->toBe((float) $bank->refresh()->balance);
});

it('shows the statement on the page', function () {
    $bank = Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 2500.00,
        'balance' => 2500.00,
    ]);
    $loan = Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 10000.00,
        'balance' => 10000.00,
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
        ->assertSee('Cash In')
        ->assertSee('Cash Out')
        ->assertSee($loan->name.' — repayment')
        ->assertSee('703.00')
        ->assertSee('1,797.00'); // closing: 2,500 - 703
});
