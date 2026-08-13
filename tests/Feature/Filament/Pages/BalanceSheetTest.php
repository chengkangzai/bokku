<?php

use App\Filament\Pages\BalanceSheet;
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

it('can render the balance sheet page in the Insights group', function () {
    livewire(BalanceSheet::class)->assertSuccessful();

    $reflectionClass = new ReflectionClass(BalanceSheet::class);
    expect($reflectionClass->getProperty('navigationGroup')->getValue())->toBe('Insights');
});

it('reconstructs per-account month-end balances and ties to net worth', function () {
    $bank = Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 3000.00,
        'balance' => 3000.00,
    ]);
    $loan = Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 2000.00,
        'balance' => 2000.00,
    ]);
    Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'name' => 'Empty Account',
        'initial_balance' => 0.00,
        'balance' => 0.00,
    ]);

    Transaction::factory()->expense()->withAmount(400.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->subMonth()->startOfMonth(),
    ]);
    Transaction::factory()->transfer()->withAmount(500.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'category_id' => null,
        'from_account_id' => $bank->id,
        'to_account_id' => $loan->id,
        'date' => now()->startOfMonth(),
    ]);

    $sheet = (new SpendingAnalysisService)->balanceSheet($this->user->id, CarbonImmutable::now(), 3);

    $bankRow = collect($sheet['assets'])->firstWhere('name', $bank->name);
    $loanRow = collect($sheet['liabilities'])->firstWhere('name', $loan->name);

    expect($bankRow['values'])->toBe([3000.0, 2600.0, 2100.0])
        ->and($bankRow['group'])->toBe('Cash & Bank')
        ->and($loanRow['values'])->toBe([2000.0, 2000.0, 1500.0])
        ->and($loanRow['group'])->toBe('Loans')
        ->and(collect($sheet['assets'])->pluck('name'))->not->toContain('Empty Account')
        ->and(end($sheet['net_worth']))->toBe(600.0) // 2,100 - 1,500
        ->and(end($sheet['net_worth']))->toBe(collect($this->user->netWorthHistory(1))->last())
        ->and(end($bankRow['values']))->toBe((float) $bank->refresh()->balance);
});

it('groups family arrangement accounts separately', function () {
    Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 100.00,
        'balance' => 100.00,
    ]);
    Account::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Due from Mum',
        'type' => 'cash',
        'initial_balance' => 5000.00,
        'balance' => 5000.00,
        'exclude_from_net_worth' => true,
    ]);
    Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'name' => 'Axia HP',
        'initial_balance' => 5000.00,
        'balance' => 5000.00,
        'exclude_from_net_worth' => true,
    ]);

    $sheet = (new SpendingAnalysisService)->balanceSheet($this->user->id, CarbonImmutable::now(), 2);

    expect(collect($sheet['assets'])->firstWhere('name', 'Due from Mum')['group'])->toBe('Receivables')
        ->and(collect($sheet['liabilities'])->firstWhere('name', 'Axia HP')['group'])->toBe('Held for family')
        ->and(end($sheet['net_worth']))->toBe(100.0); // family pair offsets

    livewire(BalanceSheet::class)
        ->assertSee('Receivables')
        ->assertSee('Held for family')
        ->assertSee('Due from Mum')
        ->assertSee('Axia HP');
});
