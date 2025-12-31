<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Payees\CreatePayeeTool;
use App\Mcp\Tools\Payees\DeletePayeeTool;
use App\Mcp\Tools\Payees\GetPayeeTool;
use App\Mcp\Tools\Payees\ListPayeesTool;
use App\Mcp\Tools\Payees\UpdatePayeeTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

describe('ListPayeesTool', function () {
    it('returns all payees for user', function () {
        Payee::factory()->count(3)->create(['user_id' => $this->user->id]);
        Payee::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListPayeesTool::class);

        $response->assertOk()
            ->assertSee('"count": 3');
    });

    it('does not return other users payees', function () {
        Payee::factory()->create(['user_id' => $this->user->id, 'name' => 'My Payee']);
        Payee::factory()->create(['user_id' => $this->otherUser->id, 'name' => 'Other Payee']);

        $response = BokkuServer::actingAs($this->user)->tool(ListPayeesTool::class);

        $response->assertOk()
            ->assertSee('My Payee')
            ->assertDontSee('Other Payee');
    });

    it('filters by active status', function () {
        Payee::factory()->active()->create(['user_id' => $this->user->id, 'name' => 'Active Payee']);
        Payee::factory()->inactive()->create(['user_id' => $this->user->id, 'name' => 'Inactive Payee']);

        $response = BokkuServer::actingAs($this->user)->tool(ListPayeesTool::class, [
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertSee('Active Payee')
            ->assertDontSee('Inactive Payee');
    });

    it('filters by inactive status', function () {
        Payee::factory()->active()->create(['user_id' => $this->user->id, 'name' => 'Active Payee']);
        Payee::factory()->inactive()->create(['user_id' => $this->user->id, 'name' => 'Inactive Payee']);

        $response = BokkuServer::actingAs($this->user)->tool(ListPayeesTool::class, [
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertSee('Inactive Payee')
            ->assertDontSee('Active Payee');
    });

    it('includes default category info', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Food & Dining',
        ]);

        Payee::factory()->create([
            'user_id' => $this->user->id,
            'default_category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListPayeesTool::class);

        $response->assertOk()
            ->assertSee('Food & Dining');
    });

    it('includes transaction count', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);
        Transaction::factory()->count(3)->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'payee_id' => $payee->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListPayeesTool::class);

        $response->assertOk()
            ->assertSee('"transaction_count": 3');
    });
});

describe('GetPayeeTool', function () {
    it('returns payee by id', function () {
        $payee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Starbucks',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetPayeeTool::class, [
            'id' => $payee->id,
        ]);

        $response->assertOk()
            ->assertSee('Starbucks');
    });

    it('returns error for non-existent payee', function () {
        $response = BokkuServer::actingAs($this->user)->tool(GetPayeeTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users payee', function () {
        $payee = Payee::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(GetPayeeTool::class, [
            'id' => $payee->id,
        ]);

        $response->assertHasErrors();
    });

    it('includes default category with type', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Shopping',
        ]);
        $payee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'default_category_id' => $category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetPayeeTool::class, [
            'id' => $payee->id,
        ]);

        $response->assertOk()
            ->assertSee('Shopping')
            ->assertSee('expense');
    });
});

describe('CreatePayeeTool', function () {
    it('creates payee with valid data', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, [
            'name' => 'Amazon',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payees', [
            'user_id' => $this->user->id,
            'name' => 'Amazon',
            'is_active' => true,
        ]);
    });

    it('creates payee with default category', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, [
            'name' => 'Grocery Store',
            'default_category_id' => $category->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payees', [
            'user_id' => $this->user->id,
            'name' => 'Grocery Store',
            'default_category_id' => $category->id,
        ]);
    });

    it('validates required name', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, []);

        $response->assertHasErrors();
    });

    it('prevents duplicate name for same user', function () {
        Payee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Starbucks',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, [
            'name' => 'Starbucks',
        ]);

        $response->assertHasErrors();
    });

    it('allows same name for different users', function () {
        Payee::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Starbucks',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, [
            'name' => 'Starbucks',
        ]);

        $response->assertOk();
    });

    it('validates default category belongs to user', function () {
        $otherCategory = Category::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, [
            'name' => 'Test Payee',
            'default_category_id' => $otherCategory->id,
        ]);

        $response->assertHasErrors();
    });

    it('can set inactive status', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreatePayeeTool::class, [
            'name' => 'Inactive Vendor',
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payees', [
            'user_id' => $this->user->id,
            'name' => 'Inactive Vendor',
            'is_active' => false,
        ]);
    });
});

describe('UpdatePayeeTool', function () {
    it('updates payee name', function () {
        $payee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdatePayeeTool::class, [
            'id' => $payee->id,
            'name' => 'New Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payees', [
            'id' => $payee->id,
            'name' => 'New Name',
        ]);
    });

    it('validates payee exists', function () {
        $response = BokkuServer::actingAs($this->user)->tool(UpdatePayeeTool::class, [
            'id' => 99999,
            'name' => 'New Name',
        ]);

        $response->assertHasErrors();
    });

    it('cannot update other users payee', function () {
        $payee = Payee::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdatePayeeTool::class, [
            'id' => $payee->id,
            'name' => 'Hacked Name',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseMissing('payees', [
            'id' => $payee->id,
            'name' => 'Hacked Name',
        ]);
    });

    it('can update default category', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdatePayeeTool::class, [
            'id' => $payee->id,
            'default_category_id' => $category->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payees', [
            'id' => $payee->id,
            'default_category_id' => $category->id,
        ]);
    });

    it('can toggle active status', function () {
        $payee = Payee::factory()->active()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdatePayeeTool::class, [
            'id' => $payee->id,
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payees', [
            'id' => $payee->id,
            'is_active' => false,
        ]);
    });

    it('prevents duplicate name when updating', function () {
        Payee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Existing Payee',
        ]);
        $payee = Payee::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'To Update',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdatePayeeTool::class, [
            'id' => $payee->id,
            'name' => 'Existing Payee',
        ]);

        $response->assertHasErrors();
    });
});

describe('DeletePayeeTool', function () {
    it('deletes payee', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(DeletePayeeTool::class, [
            'id' => $payee->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('payees', ['id' => $payee->id]);
    });

    it('cannot delete other users payee', function () {
        $payee = Payee::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(DeletePayeeTool::class, [
            'id' => $payee->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('payees', ['id' => $payee->id]);
    });

    it('returns error for non-existent payee', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeletePayeeTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('sets payee_id to null on transactions when deleted', function () {
        $payee = Payee::factory()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'payee_id' => $payee->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeletePayeeTool::class, [
            'id' => $payee->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('payees', ['id' => $payee->id]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payee_id' => null,
        ]);
    });
});
