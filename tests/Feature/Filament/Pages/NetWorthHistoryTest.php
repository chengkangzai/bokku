<?php

use App\Filament\Pages\NetWorthHistory;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render the net worth history page', function () {
    livewire(NetWorthHistory::class)
        ->assertSuccessful();
});

it('lives in the Insights navigation group', function () {
    $reflectionClass = new ReflectionClass(NetWorthHistory::class);

    $groupProperty = $reflectionClass->getProperty('navigationGroup');
    expect($groupProperty->getValue())->toBe('Insights');
});

it('only accessible by authenticated users', function () {
    auth()->logout();

    $this->get(NetWorthHistory::getUrl())
        ->assertRedirect();
});

it('shows current net worth figures and monthly history', function () {
    $bank = Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 5000.00,
        'balance' => 5000.00,
    ]);
    Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 2000.00,
        'balance' => 2000.00,
    ]);

    Transaction::factory()->expense()->withAmount(500.00)->create([
        'user_id' => $this->user->id,
        'account_id' => $bank->id,
        'date' => now()->startOfMonth(),
    ]);

    livewire(NetWorthHistory::class)
        ->assertSee('MYR 2,500.00') // net worth: 5,000 - 500 - 2,000
        ->assertSee('MYR 4,500.00') // total assets
        ->assertSee('MYR 2,000.00') // total liabilities
        ->assertSee(now()->format('M Y'));
});

it('validates the months setter', function () {
    livewire(NetWorthHistory::class)
        ->call('setMonths', 24)
        ->assertSet('months', 24)
        ->call('setMonths', 7)
        ->assertSet('months', 24)
        ->call('setMonths', 6)
        ->assertSet('months', 6);
});
