<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Categories\CreateCategoryTool;
use App\Mcp\Tools\Categories\DeleteCategoryTool;
use App\Mcp\Tools\Categories\GetCategoryTool;
use App\Mcp\Tools\Categories\ListCategoriesTool;
use App\Mcp\Tools\Categories\UpdateCategoryTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

describe('ListCategoriesTool', function () {
    it('returns all categories for user', function () {
        $categories = Category::factory()->count(3)->create(['user_id' => $this->user->id]);
        Category::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListCategoriesTool::class);

        $response->assertOk();
    });

    it('does not return other users categories', function () {
        Category::factory()->create(['user_id' => $this->user->id, 'name' => 'My Category']);
        Category::factory()->create(['user_id' => $this->otherUser->id, 'name' => 'Other Category']);

        $response = BokkuServer::actingAs($this->user)->tool(ListCategoriesTool::class);

        $response->assertOk()
            ->assertSee('My Category')
            ->assertDontSee('Other Category');
    });

    it('filters by type income', function () {
        Category::factory()->income()->create([
            'user_id' => $this->user->id,
            'name' => 'Salary Income',
        ]);
        Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Groceries Expense',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListCategoriesTool::class, [
            'type' => 'income',
        ]);

        $response->assertOk()
            ->assertSee('Salary Income')
            ->assertDontSee('Groceries Expense');
    });

    it('filters by type expense', function () {
        Category::factory()->income()->create([
            'user_id' => $this->user->id,
            'name' => 'Salary Income',
        ]);
        Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Groceries Expense',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListCategoriesTool::class, [
            'type' => 'expense',
        ]);

        $response->assertOk()
            ->assertSee('Groceries Expense')
            ->assertDontSee('Salary Income');
    });
});

describe('GetCategoryTool', function () {
    it('returns category by id', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Food & Dining',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetCategoryTool::class, [
            'id' => $category->id,
        ]);

        $response->assertOk()
            ->assertSee('Food & Dining');
    });

    it('returns error for non-existent category', function () {
        $response = BokkuServer::actingAs($this->user)->tool(GetCategoryTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users category', function () {
        $category = Category::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(GetCategoryTool::class, [
            'id' => $category->id,
        ]);

        $response->assertHasErrors();
    });

    it('includes monthly spending for expense category', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 50.00,
            'date' => now(),
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetCategoryTool::class, [
            'id' => $category->id,
        ]);

        $response->assertOk()
            ->assertSee('50');
    });
});

describe('CreateCategoryTool', function () {
    it('creates category with valid data', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateCategoryTool::class, [
            'name' => 'Entertainment',
            'type' => 'expense',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'user_id' => $this->user->id,
            'name' => 'Entertainment',
            'type' => 'expense',
        ]);
    });

    it('validates type enum', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateCategoryTool::class, [
            'name' => 'Invalid Type',
            'type' => 'invalid',
        ]);

        $response->assertHasErrors();
    });

    it('validates required fields', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateCategoryTool::class, [
            'type' => 'expense',
        ]);

        $response->assertHasErrors();
    });

    it('prevents duplicate name for same type and user', function () {
        Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Groceries',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateCategoryTool::class, [
            'name' => 'Groceries',
            'type' => 'expense',
        ]);

        $response->assertHasErrors();
    });

    it('allows same name for different types', function () {
        Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Other',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateCategoryTool::class, [
            'name' => 'Other',
            'type' => 'income',
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('categories', 2);
    });

    it('assigns category to authenticated user', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateCategoryTool::class, [
            'name' => 'User Category',
            'type' => 'income',
        ]);

        $response->assertOk();

        $category = Category::where('name', 'User Category')->first();
        expect($category->user_id)->toBe($this->user->id);
    });
});

describe('UpdateCategoryTool', function () {
    it('updates category properties', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateCategoryTool::class, [
            'id' => $category->id,
            'name' => 'New Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    });

    it('validates category exists', function () {
        $response = BokkuServer::actingAs($this->user)->tool(UpdateCategoryTool::class, [
            'id' => 99999,
            'name' => 'New Name',
        ]);

        $response->assertHasErrors();
    });

    it('cannot update other users category', function () {
        $category = Category::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateCategoryTool::class, [
            'id' => $category->id,
            'name' => 'Hacked Name',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
            'name' => 'Hacked Name',
        ]);
    });

    it('can update color and icon', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'color' => '#FF0000',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateCategoryTool::class, [
            'id' => $category->id,
            'color' => '#00FF00',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'color' => '#00FF00',
        ]);
    });
});

describe('DeleteCategoryTool', function () {
    it('deletes category with no transactions', function () {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteCategoryTool::class, [
            'id' => $category->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    });

    it('cannot delete other users category', function () {
        $category = Category::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteCategoryTool::class, [
            'id' => $category->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    });

    it('returns error for non-existent category', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeleteCategoryTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('sets category_id to null on transactions when deleted', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteCategoryTool::class, [
            'id' => $category->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => null,
        ]);
    });
});
