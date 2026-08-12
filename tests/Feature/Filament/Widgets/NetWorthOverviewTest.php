<?php

use App\Filament\Resources\Accounts\Widgets\NetWorthOverview;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('NetWorthOverview Widget Rendering', function () {
    it('can render successfully', function () {
        livewire(NetWorthOverview::class)
            ->assertSuccessful();
    });

    it('renders without any accounts', function () {
        livewire(NetWorthOverview::class)
            ->assertSuccessful()
            ->assertSee('Net Worth')
            ->assertSee('Total Assets')
            ->assertSee('Total Liabilities')
            ->assertSee('MYR 0.00');
    });
});

describe('NetWorthOverview Widget Values', function () {
    it('shows assets, liabilities and net worth', function () {
        Account::factory()->bank()->create(['user_id' => $this->user->id, 'balance' => 10000.00]);
        Account::factory()->cash()->create(['user_id' => $this->user->id, 'balance' => 500.00]);
        Account::factory()->loan()->create(['user_id' => $this->user->id, 'balance' => 4000.00]);

        livewire(NetWorthOverview::class)
            ->assertSuccessful()
            ->assertSee('MYR 10,500.00')
            ->assertSee('MYR 4,000.00')
            ->assertSee('MYR 6,500.00');
    });

    it('counts the accounts behind each total', function () {
        Account::factory()->bank()->count(2)->create(['user_id' => $this->user->id]);
        Account::factory()->creditCard()->create(['user_id' => $this->user->id]);

        livewire(NetWorthOverview::class)
            ->assertSuccessful()
            ->assertSee('2 accounts')
            ->assertSee('1 account');
    });

    it('describes the month over month change', function () {
        $account = Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'initial_balance' => 1000.00,
            'balance' => 1000.00,
        ]);

        Transaction::factory()->expense()->withAmount(100.00)->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'date' => now()->startOfMonth(),
        ]);

        livewire(NetWorthOverview::class)
            ->assertSuccessful()
            ->assertSee('MYR 900.00')
            ->assertSee('MYR -100.00 (10.0%) vs last month');
    });

    it('excludes other users accounts', function () {
        $other = User::factory()->create();

        Account::factory()->bank()->create(['user_id' => $this->user->id, 'balance' => 100.00]);
        Account::factory()->bank()->create(['user_id' => $other->id, 'balance' => 99999.00]);

        livewire(NetWorthOverview::class)
            ->assertSuccessful()
            ->assertSee('MYR 100.00')
            ->assertDontSee('MYR 99,999.00');
    });
});
