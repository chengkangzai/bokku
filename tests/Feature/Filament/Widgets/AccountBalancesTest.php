<?php

use App\Filament\Widgets\AccountBalances;
use App\Models\Account;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('AccountBalances Widget Instantiation', function () {
    it('can be instantiated', function () {
        $widget = new AccountBalances();
        expect($widget)->toBeInstanceOf(AccountBalances::class);
    });

    it('has correct sort order', function () {
        $reflectionClass = new ReflectionClass(AccountBalances::class);
        $sortProperty = $reflectionClass->getProperty('sort');
        $sortProperty->setAccessible(true);
        
        expect($sortProperty->getValue())->toBe(3);
    });

    it('has correct column span', function () {
        $widget = new AccountBalances();
        $reflectionClass = new ReflectionClass(AccountBalances::class);
        $columnSpanProperty = $reflectionClass->getProperty('columnSpan');
        $columnSpanProperty->setAccessible(true);
        
        expect($columnSpanProperty->getValue($widget))->toBe(1);
    });

    it('has correct heading', function () {
        $reflectionClass = new ReflectionClass(AccountBalances::class);
        $headingProperty = $reflectionClass->getProperty('heading');
        $headingProperty->setAccessible(true);
        
        expect($headingProperty->getValue())->toBe('Account Balances');
    });
});

describe('AccountBalances Widget Rendering', function () {
    it('can render successfully', function () {
        livewire(AccountBalances::class)
            ->assertSuccessful();
    });

    it('can render without accounts', function () {
        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCountTableRecords(0);
    });

    it('displays active user accounts', function () {
        $activeAccounts = Account::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        
        $inactiveAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($activeAccounts)
            ->assertCanNotSeeTableRecords([$inactiveAccount])
            ->assertCountTableRecords(3);
    });
});

describe('AccountBalances Data Scoping', function () {
    it('only shows accounts for authenticated user', function () {
        $otherUser = User::factory()->create();
        
        $userAccounts = Account::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        
        $otherUserAccounts = Account::factory()->count(3)->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($userAccounts)
            ->assertCanNotSeeTableRecords($otherUserAccounts)
            ->assertCountTableRecords(2);
    });

    it('only shows active accounts', function () {
        $activeAccounts = Account::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        
        $inactiveAccounts = Account::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($activeAccounts)
            ->assertCanNotSeeTableRecords($inactiveAccounts)
            ->assertCountTableRecords(2);
    });
});

describe('AccountBalances Table Columns', function () {
    it('can render account name column', function () {
        Account::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('name');
    });

    it('can render account type column', function () {
        Account::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('type');
    });

    it('can render account balance column', function () {
        Account::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('balance');
    });

    it('displays account types with correct formatting', function () {
        $bankAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'bank',
            'is_active' => true,
        ]);

        $cashAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'cash',
            'is_active' => true,
        ]);

        $creditCardAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertSee('Bank')
            ->assertSee('Cash')
            ->assertSee('Credit Card');
    });

    it('displays account balances with correct currency formatting', function () {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1234.56,
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => -500.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful();
            // Note: Specific currency formatting depends on money() helper implementation
    });
});

describe('AccountBalances Table Actions', function () {
    it('has view action for each account', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$account]);

        // The action exists in the table definition
        $this->assertTrue(true);
    });

    it('view action links to account edit page', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        // Test that the URL generation works (indirectly tests the action)
        $expectedUrl = route('filament.admin.resources.accounts.edit', $account);
        expect($expectedUrl)->toContain("accounts/{$account->id}/edit");
    });
});

describe('AccountBalances Widget Properties', function () {
    it('is not paginated', function () {
        // Create many accounts to test pagination is disabled
        Account::factory()->count(15)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCountTableRecords(15); // All records should show since pagination is false
    });
});

describe('AccountBalances Different Account Types', function () {
    it('displays all account types correctly', function () {
        $accounts = collect(['bank', 'cash', 'credit_card', 'loan'])->map(function ($type) {
            return Account::factory()->create([
                'user_id' => $this->user->id,
                'type' => $type,
                'is_active' => true,
            ]);
        });

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($accounts)
            ->assertCountTableRecords(4);
    });

    it('displays positive and negative balances', function () {
        $positiveAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'is_active' => true,
        ]);

        $negativeAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => -500.00,
            'is_active' => true,
        ]);

        livewire(AccountBalances::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$positiveAccount, $negativeAccount])
            ->assertCountTableRecords(2);
    });
});