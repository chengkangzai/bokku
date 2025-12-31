<?php

use App\Filament\Widgets\BudgetOverview;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
});

describe('BudgetOverview Widget', function () {
    it('can render widget when budgets exist', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        livewire(BudgetOverview::class)
            ->assertOk();
    });

    it('is hidden when no budgets exist', function () {
        expect(BudgetOverview::canView())->toBeFalse();
    });

    it('can display budget records in table', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Groceries',
        ]);

        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        livewire(BudgetOverview::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$budget]);
    });

    it('displays multiple budget records', function () {
        $categories = Category::factory()->expense()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $budgets = [];
        foreach ($categories as $category) {
            $budgets[] = Budget::factory()->create([
                'user_id' => $this->user->id,
                'category_id' => $category->id,
                'amount' => 400.00,
            ]);
        }

        livewire(BudgetOverview::class)
            ->assertOk()
            ->assertCanSeeTableRecords($budgets);
    });

    it('only shows active budgets', function () {
        $category1 = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $category2 = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $activeBudget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category1->id,
            'amount' => 400.00,
            'is_active' => true,
        ]);

        $inactiveBudget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category2->id,
            'amount' => 300.00,
            'is_active' => false,
        ]);

        livewire(BudgetOverview::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$activeBudget])
            ->assertCanNotSeeTableRecords([$inactiveBudget]);
    });

    it('only shows budgets for authenticated user', function () {
        $otherUser = User::factory()->create();

        $userCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $otherCategory = Category::factory()->expense()->create(['user_id' => $otherUser->id]);

        $userBudget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $userCategory->id,
            'amount' => 400.00,
        ]);

        $otherBudget = Budget::factory()->create([
            'user_id' => $otherUser->id,
            'category_id' => $otherCategory->id,
            'amount' => 300.00,
        ]);

        livewire(BudgetOverview::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$userBudget])
            ->assertCanNotSeeTableRecords([$otherBudget]);
    });

    it('can see budget with transaction data', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 400.00,
        ]);

        // Add some spending
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'account_id' => $this->account->id,
            'amount' => 160.00,
            'date' => now(),
        ]);

        livewire(BudgetOverview::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$budget]);
    });
});
