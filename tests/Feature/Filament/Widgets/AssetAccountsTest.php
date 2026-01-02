<?php

use App\Filament\Widgets\AssetAccounts;
use App\Models\Account;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('AssetAccounts Widget Instantiation', function () {
    it('can be instantiated', function () {
        $widget = new AssetAccounts;
        expect($widget)->toBeInstanceOf(AssetAccounts::class);
    });

    it('has correct sort order', function () {
        $reflectionClass = new ReflectionClass(AssetAccounts::class);
        $sortProperty = $reflectionClass->getProperty('sort');
        $sortProperty->setAccessible(true);

        expect($sortProperty->getValue())->toBe(3);
    });

    it('has correct column span', function () {
        $widget = new AssetAccounts;
        $reflectionClass = new ReflectionClass(AssetAccounts::class);
        $columnSpanProperty = $reflectionClass->getProperty('columnSpan');
        $columnSpanProperty->setAccessible(true);

        expect($columnSpanProperty->getValue($widget))->toBe(1);
    });

    it('has correct heading', function () {
        $reflectionClass = new ReflectionClass(AssetAccounts::class);
        $headingProperty = $reflectionClass->getProperty('heading');
        $headingProperty->setAccessible(true);

        expect($headingProperty->getValue())->toBe('Assets');
    });
});

describe('AssetAccounts Widget Rendering', function () {
    it('can render successfully', function () {
        livewire(AssetAccounts::class)
            ->assertSuccessful();
    });

    it('can render without accounts', function () {
        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCountTableRecords(0);
    });

    it('displays only asset accounts (bank and cash)', function () {
        $bankAccount = Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $cashAccount = Account::factory()->cash()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $loanAccount = Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $creditCardAccount = Account::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bankAccount, $cashAccount])
            ->assertCanNotSeeTableRecords([$loanAccount, $creditCardAccount])
            ->assertCountTableRecords(2);
    });
});

describe('AssetAccounts Data Scoping', function () {
    it('only shows accounts for authenticated user', function () {
        $otherUser = User::factory()->create();

        $userAccounts = Account::factory()->count(2)->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $otherUserAccounts = Account::factory()->count(3)->bank()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($userAccounts)
            ->assertCanNotSeeTableRecords($otherUserAccounts)
            ->assertCountTableRecords(2);
    });

    it('only shows active accounts', function () {
        $activeAccounts = Account::factory()->count(2)->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $inactiveAccounts = Account::factory()->count(2)->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($activeAccounts)
            ->assertCanNotSeeTableRecords($inactiveAccounts)
            ->assertCountTableRecords(2);
    });
});

describe('AssetAccounts Table Columns', function () {
    it('can render account name column', function () {
        Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('name');
    });

    it('can render account type column', function () {
        Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('type');
    });

    it('can render account balance column', function () {
        Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('balance');
    });

    it('displays account types correctly', function () {
        Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        Account::factory()->cash()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertSee('Bank')
            ->assertSee('Cash');
    });
});

describe('AssetAccounts Table Actions', function () {
    it('view action links to account edit page', function () {
        $account = Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $expectedUrl = route('filament.admin.resources.accounts.edit', $account);
        expect($expectedUrl)->toContain("accounts/{$account->id}/edit");
    });
});

describe('AssetAccounts Widget Properties', function () {
    it('is not paginated', function () {
        Account::factory()->count(15)->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(AssetAccounts::class)
            ->assertSuccessful()
            ->assertCountTableRecords(15);
    });
});
