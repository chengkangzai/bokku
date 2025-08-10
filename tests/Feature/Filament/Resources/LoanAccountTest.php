<?php

use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\AccountResource\Pages\EditAccount;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Models\Account;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Loan Account Resource', function () {
    it('can create a loan account with negative balance', function () {
        livewire(CreateAccount::class)
            ->fillForm([
                'name' => 'Car Loan - Honda City',
                'type' => 'loan',
                'initial_balance' => -60000,
                'currency' => 'MYR',
                'notes' => 'Monthly payment: RM 1,200, Due on 15th',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Account::class, [
            'name' => 'Car Loan - Honda City',
            'type' => 'loan',
            'initial_balance' => -6000000, // DB stores cents
            'user_id' => $this->user->id,
        ]);
    });

    it('displays loan balance as positive outstanding amount in table', function () {
        $loan = Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'name' => 'Home Loan',
            'balance' => -250000,
            'currency' => 'MYR',
        ]);

        livewire(ListAccounts::class)
            ->assertCanSeeTableRecords([$loan])
            ->assertTableColumnFormattedStateSet('balance', 'MYR 250,000.00', $loan);
    });

    it('shows loan-specific helper text in form', function () {
        livewire(CreateAccount::class)
            ->fillForm(['type' => 'loan'])
            ->assertSee('Enter as negative amount')
            ->assertSee('Total Amount Owed');
    });

    it('shows correct placeholder for loan notes', function () {
        livewire(CreateAccount::class)
            ->fillForm(['type' => 'loan'])
            ->assertSee('Monthly payment: RM');
    });

    it('can update loan account balance through transactions', function () {
        $loan = Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'initial_balance' => -10000,
            'balance' => -10000,
            'currency' => 'MYR',
        ]);

        // Simulate updating balance after payment
        $loan->balance = -8800;
        $loan->save();

        livewire(EditAccount::class, ['record' => $loan->getRouteKey()])
            ->assertFormSet([
                'name' => $loan->name,
                'type' => 'loan',
            ]);

        expect((float) $loan->refresh()->balance)->toBe(-8800.0);
        expect($loan->formatted_balance)->toBe('MYR 8,800.00');
    });

    it('filters loan accounts correctly', function () {
        $loan = Account::factory()->loan()->create(['user_id' => $this->user->id]);
        $bank = Account::factory()->bank()->create(['user_id' => $this->user->id]);
        $cash = Account::factory()->cash()->create(['user_id' => $this->user->id]);

        livewire(ListAccounts::class)
            ->filterTable('type', 'loan')
            ->assertCanSeeTableRecords([$loan])
            ->assertCanNotSeeTableRecords([$bank, $cash]);
    });
});
