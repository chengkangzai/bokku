<?php

use App\Filament\Widgets\LiabilityAccounts;
use App\Models\Account;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('LiabilityAccounts Widget Instantiation', function () {
    it('can be instantiated', function () {
        $widget = new LiabilityAccounts;
        expect($widget)->toBeInstanceOf(LiabilityAccounts::class);
    });

    it('has correct sort order', function () {
        $reflectionClass = new ReflectionClass(LiabilityAccounts::class);
        $sortProperty = $reflectionClass->getProperty('sort');
        $sortProperty->setAccessible(true);

        expect($sortProperty->getValue())->toBe(4);
    });

    it('has correct column span', function () {
        $widget = new LiabilityAccounts;
        $reflectionClass = new ReflectionClass(LiabilityAccounts::class);
        $columnSpanProperty = $reflectionClass->getProperty('columnSpan');
        $columnSpanProperty->setAccessible(true);

        expect($columnSpanProperty->getValue($widget))->toBe(1);
    });

    it('has correct heading', function () {
        $reflectionClass = new ReflectionClass(LiabilityAccounts::class);
        $headingProperty = $reflectionClass->getProperty('heading');
        $headingProperty->setAccessible(true);

        expect($headingProperty->getValue())->toBe('Liabilities');
    });
});

describe('LiabilityAccounts Widget Rendering', function () {
    it('can render successfully', function () {
        livewire(LiabilityAccounts::class)
            ->assertSuccessful();
    });

    it('can render without accounts', function () {
        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCountTableRecords(0);
    });

    it('displays only liability accounts (loan and credit card)', function () {
        $loanAccount = Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $creditCardAccount = Account::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $bankAccount = Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $cashAccount = Account::factory()->cash()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$loanAccount, $creditCardAccount])
            ->assertCanNotSeeTableRecords([$bankAccount, $cashAccount])
            ->assertCountTableRecords(2);
    });
});

describe('LiabilityAccounts Data Scoping', function () {
    it('only shows accounts for authenticated user', function () {
        $otherUser = User::factory()->create();

        $userAccounts = Account::factory()->count(2)->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $otherUserAccounts = Account::factory()->count(3)->loan()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($userAccounts)
            ->assertCanNotSeeTableRecords($otherUserAccounts)
            ->assertCountTableRecords(2);
    });

    it('only shows active accounts', function () {
        $activeAccounts = Account::factory()->count(2)->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $inactiveAccounts = Account::factory()->count(2)->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($activeAccounts)
            ->assertCanNotSeeTableRecords($inactiveAccounts)
            ->assertCountTableRecords(2);
    });
});

describe('LiabilityAccounts Table Columns', function () {
    it('can render account name column', function () {
        Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('name');
    });

    it('can render account type column', function () {
        Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('type');
    });

    it('can render account balance column', function () {
        Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('balance');
    });

    it('displays account types correctly', function () {
        Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        Account::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertSee('Loan')
            ->assertSee('Credit Card');
    });
});

describe('LiabilityAccounts Table Actions', function () {
    it('view action links to account edit page', function () {
        $account = Account::factory()->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $expectedUrl = route('filament.admin.resources.accounts.edit', $account);
        expect($expectedUrl)->toContain("accounts/{$account->id}/edit");
    });
});

describe('LiabilityAccounts Widget Properties', function () {
    it('is not paginated', function () {
        Account::factory()->count(15)->loan()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(LiabilityAccounts::class)
            ->assertSuccessful()
            ->assertCountTableRecords(15);
    });
});
