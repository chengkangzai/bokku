<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Accounts\AdjustBalanceTool;
use App\Mcp\Tools\Accounts\CreateAccountTool;
use App\Mcp\Tools\Accounts\DeleteAccountTool;
use App\Mcp\Tools\Accounts\GetAccountTool;
use App\Mcp\Tools\Accounts\ListAccountsTool;
use App\Mcp\Tools\Accounts\UpdateAccountTool;
use App\Models\Account;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

describe('ListAccountsTool', function () {
    it('returns all accounts for authenticated user', function () {
        $accounts = Account::factory()->count(3)->create(['user_id' => $this->user->id]);
        Account::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListAccountsTool::class);

        $response->assertOk()
            ->assertSee($accounts[0]->name)
            ->assertSee($accounts[1]->name)
            ->assertSee($accounts[2]->name);
    });

    it('does not return other users accounts', function () {
        Account::factory()->create(['user_id' => $this->user->id, 'name' => 'My Account']);
        Account::factory()->create(['user_id' => $this->otherUser->id, 'name' => 'Other Account']);

        $response = BokkuServer::actingAs($this->user)->tool(ListAccountsTool::class);

        $response->assertOk()
            ->assertSee('My Account')
            ->assertDontSee('Other Account');
    });

    it('includes formatted balance', function () {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Account',
            'balance' => 100.50,
            'currency' => 'USD',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListAccountsTool::class);

        $response->assertOk()
            ->assertSee('100.50');
    });

    it('returns empty list when user has no accounts', function () {
        $response = BokkuServer::actingAs($this->user)->tool(ListAccountsTool::class);

        $response->assertOk();
    });
});

describe('GetAccountTool', function () {
    it('returns account by id', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Savings Account',
            'balance' => 500.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetAccountTool::class, [
            'id' => $account->id,
        ]);

        $response->assertOk()
            ->assertSee('Savings Account')
            ->assertSee('500.00');
    });

    it('returns error for non-existent account', function () {
        $response = BokkuServer::actingAs($this->user)->tool(GetAccountTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users account', function () {
        $account = Account::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(GetAccountTool::class, [
            'id' => $account->id,
        ]);

        $response->assertHasErrors();
    });

    it('includes account type and currency', function () {
        $account = Account::factory()->bank()->create([
            'user_id' => $this->user->id,
            'currency' => 'EUR',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetAccountTool::class, [
            'id' => $account->id,
        ]);

        $response->assertOk()
            ->assertSee('bank')
            ->assertSee('EUR');
    });
});

describe('CreateAccountTool', function () {
    it('creates account with valid data', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateAccountTool::class, [
            'name' => 'New Savings',
            'type' => 'bank',
            'currency' => 'USD',
            'initial_balance' => 1000.00,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $this->user->id,
            'name' => 'New Savings',
            'type' => 'bank',
            'currency' => 'USD',
            'initial_balance' => 100000,
        ]);
    });

    it('validates required fields', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateAccountTool::class, [
            'type' => 'bank',
        ]);

        $response->assertHasErrors();
    });

    it('validates account type enum', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateAccountTool::class, [
            'name' => 'Test Account',
            'type' => 'invalid_type',
            'currency' => 'USD',
        ]);

        $response->assertHasErrors();
    });

    it('creates account with default balance of zero', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateAccountTool::class, [
            'name' => 'Zero Balance Account',
            'type' => 'cash',
            'currency' => 'USD',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $this->user->id,
            'name' => 'Zero Balance Account',
            'balance' => 0,
        ]);
    });

    it('assigns account to authenticated user', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateAccountTool::class, [
            'name' => 'User Account',
            'type' => 'bank',
            'currency' => 'USD',
        ]);

        $response->assertOk();

        $account = Account::where('name', 'User Account')->first();
        expect($account->user_id)->toBe($this->user->id);
    });
});

describe('UpdateAccountTool', function () {
    it('updates account properties', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateAccountTool::class, [
            'id' => $account->id,
            'name' => 'New Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'New Name',
        ]);
    });

    it('validates account exists', function () {
        $response = BokkuServer::actingAs($this->user)->tool(UpdateAccountTool::class, [
            'id' => 99999,
            'name' => 'New Name',
        ]);

        $response->assertHasErrors();
    });

    it('cannot update other users account', function () {
        $account = Account::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateAccountTool::class, [
            'id' => $account->id,
            'name' => 'Hacked Name',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
            'name' => 'Hacked Name',
        ]);
    });

    it('can update is_active status', function () {
        $account = Account::factory()->active()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateAccountTool::class, [
            'id' => $account->id,
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'is_active' => false,
        ]);
    });
});

describe('DeleteAccountTool', function () {
    it('deletes account with no transactions', function () {
        $account = Account::factory()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteAccountTool::class, [
            'id' => $account->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    });

    it('returns error if account has transactions', function () {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $account->transactions()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 100,
            'date' => now(),
            'description' => 'Test transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteAccountTool::class, [
            'id' => $account->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    });

    it('cannot delete other users account', function () {
        $account = Account::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteAccountTool::class, [
            'id' => $account->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    });

    it('returns error for non-existent account', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeleteAccountTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });
});

describe('AdjustBalanceTool', function () {
    it('creates adjustment transaction', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'initial_balance' => 100.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(AdjustBalanceTool::class, [
            'account_id' => $account->id,
            'new_balance' => 150.00,
            'description' => 'Balance adjustment',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'description' => 'Balance adjustment',
        ]);
    });

    it('updates account balance', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'initial_balance' => 100.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(AdjustBalanceTool::class, [
            'account_id' => $account->id,
            'new_balance' => 200.00,
        ]);

        $response->assertOk();

        $account->refresh();
        expect($account->balance)->toBe(200.00);
    });

    it('handles negative adjustment (balance decrease)', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 500.00,
            'initial_balance' => 500.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(AdjustBalanceTool::class, [
            'account_id' => $account->id,
            'new_balance' => 300.00,
        ]);

        $response->assertOk();

        $account->refresh();
        expect($account->balance)->toBe(300.00);
    });

    it('cannot adjust other users account', function () {
        $account = Account::factory()->create([
            'user_id' => $this->otherUser->id,
            'balance' => 100.00,
            'initial_balance' => 100.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(AdjustBalanceTool::class, [
            'account_id' => $account->id,
            'new_balance' => 500.00,
        ]);

        $response->assertHasErrors();

        $account->refresh();
        expect($account->balance)->toBe(100.00);
    });

    it('marks adjustment transaction as reconciled', function () {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'initial_balance' => 100.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(AdjustBalanceTool::class, [
            'account_id' => $account->id,
            'new_balance' => 150.00,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'is_reconciled' => true,
        ]);
    });
});
