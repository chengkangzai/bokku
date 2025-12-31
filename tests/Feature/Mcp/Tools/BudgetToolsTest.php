<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Budgets\CreateBudgetTool;
use App\Mcp\Tools\Budgets\DeleteBudgetTool;
use App\Mcp\Tools\Budgets\GetBudgetTool;
use App\Mcp\Tools\Budgets\ListBudgetsTool;
use App\Mcp\Tools\Budgets\UpdateBudgetTool;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

describe('ListBudgetsTool', function () {
    it('returns all budgets for user', function () {
        $category1 = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $category2 = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $category1->id]);
        Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $category2->id]);

        $otherCategory = Category::factory()->expense()->create(['user_id' => $this->otherUser->id]);
        Budget::factory()->create(['user_id' => $this->otherUser->id, 'category_id' => $otherCategory->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListBudgetsTool::class);

        $response->assertOk()
            ->assertSee('"count": 2');
    });

    it('does not return other users budgets', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'My Category']);
        Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

        $otherCategory = Category::factory()->expense()->create(['user_id' => $this->otherUser->id, 'name' => 'Other Category']);
        Budget::factory()->create(['user_id' => $this->otherUser->id, 'category_id' => $otherCategory->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListBudgetsTool::class);

        $response->assertOk()
            ->assertSee('My Category')
            ->assertDontSee('Other Category');
    });

    it('filters by active status', function () {
        $category1 = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Active Budget']);
        $category2 = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Inactive Budget']);

        Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $category1->id, 'is_active' => true]);
        Budget::factory()->inactive()->create(['user_id' => $this->user->id, 'category_id' => $category2->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListBudgetsTool::class, [
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertSee('Active Budget')
            ->assertDontSee('Inactive Budget');
    });

    it('filters by category_id', function () {
        $category1 = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Groceries']);
        $category2 = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Entertainment']);

        Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $category1->id]);
        Budget::factory()->create(['user_id' => $this->user->id, 'category_id' => $category2->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListBudgetsTool::class, [
            'category_id' => $category1->id,
        ]);

        $response->assertOk()
            ->assertSee('Groceries')
            ->assertDontSee('Entertainment');
    });

    it('includes spending progress fields', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        Budget::factory()->monthly()->withAmount(500.00)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListBudgetsTool::class);

        $response->assertOk()
            ->assertSee('spent_amount')
            ->assertSee('remaining_amount')
            ->assertSee('progress_percentage')
            ->assertSee('status');
    });
});

describe('GetBudgetTool', function () {
    it('returns budget by id', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Food & Dining',
        ]);
        $budget = Budget::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 300.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetBudgetTool::class, [
            'id' => $budget->id,
        ]);

        $response->assertOk()
            ->assertSee('Food & Dining')
            ->assertSee('monthly');
    });

    it('returns error for non-existent budget', function () {
        $response = BokkuServer::actingAs($this->user)->tool(GetBudgetTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users budget', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->otherUser->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->otherUser->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetBudgetTool::class, [
            'id' => $budget->id,
        ]);

        $response->assertHasErrors();
    });

    it('includes spending calculations', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->monthly()->withAmount(500.00)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetBudgetTool::class, [
            'id' => $budget->id,
        ]);

        $response->assertOk()
            ->assertSee('100')
            ->assertSee('current_period_start')
            ->assertSee('current_period_end');
    });
});

describe('CreateBudgetTool', function () {
    it('creates budget with valid data', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 500.00,
            'period' => 'monthly',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'period' => 'monthly',
            'is_active' => true,
        ]);
    });

    it('validates required fields', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'amount' => 500.00,
        ]);

        $response->assertHasErrors();
    });

    it('validates period enum', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 500.00,
            'period' => 'invalid',
        ]);

        $response->assertHasErrors();
    });

    it('validates category belongs to user', function () {
        $otherCategory = Category::factory()->expense()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $otherCategory->id,
            'amount' => 500.00,
            'period' => 'monthly',
        ]);

        $response->assertHasErrors();
    });

    it('prevents duplicate budget for same category', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 500.00,
            'period' => 'monthly',
        ]);

        $response->assertHasErrors();
    });

    it('creates budget with custom start date', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 500.00,
            'period' => 'monthly',
            'start_date' => '2025-01-01',
        ]);

        $response->assertOk();

        $budget = Budget::where('user_id', $this->user->id)
            ->where('category_id', $category->id)
            ->first();

        expect($budget->start_date->toDateString())->toBe('2025-01-01');
    });

    it('creates budget with auto rollover', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 500.00,
            'period' => 'monthly',
            'auto_rollover' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'auto_rollover' => true,
        ]);
    });

    it('supports weekly period', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 100.00,
            'period' => 'weekly',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'period' => 'weekly',
        ]);
    });

    it('supports annual period', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateBudgetTool::class, [
            'category_id' => $category->id,
            'amount' => 5000.00,
            'period' => 'annual',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'period' => 'annual',
        ]);
    });
});

describe('UpdateBudgetTool', function () {
    it('updates budget amount', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 500.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateBudgetTool::class, [
            'id' => $budget->id,
            'amount' => 750.00,
        ]);

        $response->assertOk();

        $budget->refresh();
        expect($budget->amount)->toBe(750.00);
    });

    it('validates budget exists', function () {
        $response = BokkuServer::actingAs($this->user)->tool(UpdateBudgetTool::class, [
            'id' => 99999,
            'amount' => 500.00,
        ]);

        $response->assertHasErrors();
    });

    it('cannot update other users budget', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->otherUser->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->otherUser->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateBudgetTool::class, [
            'id' => $budget->id,
            'amount' => 1000.00,
        ]);

        $response->assertHasErrors();
    });

    it('can update period', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateBudgetTool::class, [
            'id' => $budget->id,
            'period' => 'weekly',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'period' => 'weekly',
        ]);
    });

    it('can toggle active status', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateBudgetTool::class, [
            'id' => $budget->id,
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'is_active' => false,
        ]);
    });

    it('can toggle auto rollover', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'auto_rollover' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateBudgetTool::class, [
            'id' => $budget->id,
            'auto_rollover' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'auto_rollover' => true,
        ]);
    });
});

describe('DeleteBudgetTool', function () {
    it('deletes budget', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteBudgetTool::class, [
            'id' => $budget->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    });

    it('cannot delete other users budget', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->otherUser->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->otherUser->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteBudgetTool::class, [
            'id' => $budget->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    });

    it('returns error for non-existent budget', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeleteBudgetTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });
});
