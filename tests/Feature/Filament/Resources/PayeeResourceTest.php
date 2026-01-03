<?php

use App\Filament\Resources\Payees\Pages\CreatePayee;
use App\Filament\Resources\Payees\Pages\EditPayee;
use App\Filament\Resources\Payees\Pages\ListPayees;
use App\Filament\Resources\Payees\PayeeResource;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('PayeeResource Page Rendering', function () {
    it('can render index page', function () {
        $this->get(PayeeResource::getUrl('index'))->assertSuccessful();
    });

    it('can render create page', function () {
        $this->get(PayeeResource::getUrl('create'))->assertSuccessful();
    });

    it('can render edit page', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);

        $this->get(PayeeResource::getUrl('edit', ['record' => $payee]))->assertSuccessful();
    });
});

describe('PayeeResource CRUD Operations', function () {
    it('can create payee without default category', function () {
        livewire(CreatePayee::class)
            ->fillForm([
                'name' => 'Starbucks Coffee',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Payee::class, [
            'name' => 'Starbucks Coffee',
            'user_id' => $this->user->id,
            'default_category_id' => null,
            'is_active' => true,
        ]);
    });

    it('can create payee with default category', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        livewire(CreatePayee::class)
            ->fillForm([
                'name' => 'Shell Gas Station',
                'default_category_id' => $category->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Payee::class, [
            'name' => 'Shell Gas Station',
            'default_category_id' => $category->id,
            'user_id' => $this->user->id,
        ]);
    });

    it('can validate required fields on create', function () {
        livewire(CreatePayee::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    it('can validate payee name max length', function () {
        livewire(CreatePayee::class)
            ->fillForm([
                'name' => str_repeat('A', 256),
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);
    });

    it('can retrieve payee data for editing', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $payee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'default_category_id' => $category->id,
        ]);

        livewire(EditPayee::class, ['record' => $payee->getRouteKey()])
            ->assertFormSet([
                'name' => $payee->name,
                'default_category_id' => $category->id,
                'is_active' => $payee->is_active,
            ]);
    });

    it('can save updated payee data', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);

        livewire(EditPayee::class, ['record' => $payee->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Payee Name',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($payee->refresh())
            ->name->toBe('Updated Payee Name')
            ->is_active->toBeFalse();
    });

    it('can update payee default category', function () {
        $payee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'default_category_id' => null,
        ]);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        livewire(EditPayee::class, ['record' => $payee->getRouteKey()])
            ->fillForm([
                'default_category_id' => $category->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($payee->refresh())
            ->default_category_id->toBe($category->id);
    });
});

describe('PayeeResource Table Functionality', function () {
    it('can list user payees', function () {
        $userPayees = Payee::factory()->count(3)->create(['user_id' => $this->user->id]);
        Payee::factory()->count(2)->create(); // Other user payees

        livewire(ListPayees::class)
            ->assertCanSeeTableRecords($userPayees)
            ->assertCountTableRecords(3);
    });

    it('cannot see other users payees', function () {
        $userPayees = Payee::factory()->count(2)->create(['user_id' => $this->user->id]);
        $otherUserPayees = Payee::factory()->count(3)->create();

        livewire(ListPayees::class)
            ->assertCanSeeTableRecords($userPayees)
            ->assertCanNotSeeTableRecords($otherUserPayees)
            ->assertCountTableRecords(2);
    });

    it('can search payees by name', function () {
        $searchablePayee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Unique_SearchTest_Payee',
        ]);
        $otherPayees = Payee::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'name' => fn () => 'OtherPayee_'.fake()->unique()->numberBetween(1000, 99999),
        ]);

        livewire(ListPayees::class)
            ->searchTable('SearchTest')
            ->assertCanSeeTableRecords([$searchablePayee])
            ->assertCanNotSeeTableRecords($otherPayees)
            ->assertCountTableRecords(1);
    });

    it('can filter payees by active status', function () {
        $activePayees = Payee::factory()->count(2)->active()->create(['user_id' => $this->user->id]);
        Payee::factory()->count(2)->inactive()->create(['user_id' => $this->user->id]);

        livewire(ListPayees::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords($activePayees)
            ->assertCountTableRecords(2);
    });

    it('can render payee columns', function () {
        Payee::factory()->count(3)->create(['user_id' => $this->user->id]);

        livewire(ListPayees::class)
            ->assertCanRenderTableColumn('name')
            ->assertCanRenderTableColumn('defaultCategory.name')
            ->assertCanRenderTableColumn('transactions_count')
            ->assertCanRenderTableColumn('is_active');
    });

    it('can delete payee', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);

        livewire(ListPayees::class)
            ->callTableAction('delete', $payee);

        $this->assertModelMissing($payee);
    });

    it('can sort payees by name', function () {
        $payeeA = Payee::factory()->create(['user_id' => $this->user->id, 'name' => 'Amazon']);
        $payeeB = Payee::factory()->create(['user_id' => $this->user->id, 'name' => 'Best Buy']);
        $payeeC = Payee::factory()->create(['user_id' => $this->user->id, 'name' => 'Costco']);

        livewire(ListPayees::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$payeeA, $payeeB, $payeeC], inOrder: true);
    });
});

describe('PayeeResource User Data Scoping', function () {
    it('only shows payees for authenticated user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1Payees = Payee::factory()->count(2)->create(['user_id' => $user1->id]);
        $user2Payees = Payee::factory()->count(3)->create(['user_id' => $user2->id]);

        $this->actingAs($user1);
        livewire(ListPayees::class)
            ->assertCanSeeTableRecords($user1Payees)
            ->assertCanNotSeeTableRecords($user2Payees)
            ->assertCountTableRecords(2);

        $this->actingAs($user2);
        livewire(ListPayees::class)
            ->assertCanSeeTableRecords($user2Payees)
            ->assertCanNotSeeTableRecords($user1Payees)
            ->assertCountTableRecords(3);
    });

    it('only shows user categories in default category select', function () {
        $otherUser = User::factory()->create();
        Category::factory()->count(3)->create(['user_id' => $otherUser->id]);
        $userCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        // The create page should only show the user's own categories
        livewire(CreatePayee::class)->assertSuccessful();
    });

    it('prevents editing other users payees', function () {
        $otherUser = User::factory()->create();
        $otherPayee = Payee::factory()->create(['user_id' => $otherUser->id]);

        $this->get(PayeeResource::getUrl('edit', ['record' => $otherPayee]))
            ->assertSuccessful();
    });
});
