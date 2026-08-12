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

it('toggles to an own-only lens that skips excluded accounts', function () {
    Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 3000.00,
        'balance' => 3000.00,
    ]);
    Account::factory()->loan()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 8000.00,
        'balance' => 8000.00,
        'exclude_from_net_worth' => true,
    ]);

    livewire(NetWorthHistory::class)
        ->assertSee('Own only')
        ->assertSee('MYR 8,000.00')
        ->set('ownOnly', true)
        ->assertSee('MYR 0.00')
        ->assertSee('MYR 3,000.00');
});

it('keeps the all lens when no accounts are excluded', function () {
    Account::factory()->bank()->create([
        'user_id' => $this->user->id,
        'initial_balance' => 1000.00,
        'balance' => 1000.00,
    ]);

    livewire(NetWorthHistory::class)
        ->assertDontSee('Own only')
        ->set('ownOnly', true)
        ->assertSet('ownOnly', false);
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
