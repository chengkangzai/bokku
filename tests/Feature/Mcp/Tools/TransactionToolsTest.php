<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Transactions\BulkReconcileTool;
use App\Mcp\Tools\Transactions\CreateTransactionTool;
use App\Mcp\Tools\Transactions\DeleteTransactionTool;
use App\Mcp\Tools\Transactions\GetTransactionTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Mcp\Tools\Transactions\ReconcileTransactionTool;
use App\Mcp\Tools\Transactions\UpdateTransactionTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'balance' => 1000.00,
        'initial_balance' => 1000.00,
    ]);
});

describe('ListTransactionsTool', function () {
    it('returns transactions for user', function () {
        $transactions = Transaction::factory()->count(3)->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);
        Transaction::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class);

        $response->assertOk()
            ->assertSee($transactions[0]->description);
    });

    it('does not return other users transactions', function () {
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'My Transaction',
        ]);
        Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'description' => 'Other Transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class);

        $response->assertOk()
            ->assertSee('My Transaction')
            ->assertDontSee('Other Transaction');
    });

    it('filters by account', function () {
        $account2 = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 500.00,
            'initial_balance' => 500.00,
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Account 1 Transaction',
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $account2->id,
            'description' => 'Account 2 Transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'account_id' => $this->account->id,
        ]);

        $response->assertOk()
            ->assertSee('Account 1 Transaction')
            ->assertDontSee('Account 2 Transaction');
    });

    it('filters by date range', function () {
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Old Transaction',
            'date' => now()->subMonths(2),
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Recent Transaction',
            'date' => now(),
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'from_date' => now()->subMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertSee('Recent Transaction')
            ->assertDontSee('Old Transaction');
    });

    it('filters by type', function () {
        Transaction::factory()->income()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Income Transaction',
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Expense Transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'type' => 'income',
        ]);

        $response->assertOk()
            ->assertSee('Income Transaction')
            ->assertDontSee('Expense Transaction');
    });

    it('paginates results', function () {
        Transaction::factory()->count(25)->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'per_page' => 10,
        ]);

        $response->assertOk();
    });
});

describe('GetTransactionTool', function () {
    it('returns transaction with relationships', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Test Transaction',
            'amount' => 50.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk()
            ->assertSee('Test Transaction')
            ->assertSee('50.00');
    });

    it('returns error for non-existent transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(GetTransactionTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertHasErrors();
    });
});

describe('CreateTransactionTool', function () {
    it('creates income transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'income',
            'account_id' => $this->account->id,
            'amount' => 500.00,
            'description' => 'Salary payment',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'income',
            'description' => 'Salary payment',
        ]);
    });

    it('creates expense transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 100.00,
            'description' => 'Grocery shopping',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'description' => 'Grocery shopping',
        ]);
    });

    it('creates transfer transaction', function () {
        $toAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 500.00,
            'initial_balance' => 500.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'from_account_id' => $this->account->id,
            'to_account_id' => $toAccount->id,
            'amount' => 200.00,
            'description' => 'Transfer to savings',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'type' => 'transfer',
            'from_account_id' => $this->account->id,
            'to_account_id' => $toAccount->id,
        ]);
    });

    it('validates transfer requires both accounts', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'transfer',
            'from_account_id' => $this->account->id,
            'amount' => 200.00,
            'description' => 'Transfer without destination',
            'date' => now()->toDateString(),
        ]);

        $response->assertHasErrors();
    });

    it('updates account balances', function () {
        $initialBalance = $this->account->balance;

        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 100.00,
            'description' => 'Test expense',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->account->refresh();
        expect($this->account->balance)->toBe($initialBalance - 100.00);
    });

    it('validates required fields', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
        ]);

        $response->assertHasErrors();
    });

    it('assigns transaction to authenticated user', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 50.00,
            'description' => 'User transaction',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk();

        $transaction = Transaction::where('description', 'User transaction')->first();
        expect($transaction->user_id)->toBe($this->user->id);
    });

    it('can attach category to transaction', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 75.00,
            'description' => 'Categorized expense',
            'date' => now()->toDateString(),
            'category_id' => $category->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Categorized expense',
            'category_id' => $category->id,
        ]);
    });
});

describe('UpdateTransactionTool', function () {
    it('updates transaction', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Old Description',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'description' => 'New Description',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'description' => 'New Description',
        ]);
    });

    it('validates transaction exists', function () {
        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => 99999,
            'description' => 'New Description',
        ]);

        $response->assertHasErrors();
    });

    it('cannot update other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'description' => 'Other User Transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'description' => 'Hacked Description',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id,
            'description' => 'Hacked Description',
        ]);
    });

    it('can update amount and recalculates balance', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 100.00,
        ]);

        $balanceAfterCreate = $this->account->refresh()->balance;

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'amount' => 150.00,
        ]);

        $response->assertOk();

        $this->account->refresh();
        expect($this->account->balance)->toBe($balanceAfterCreate - 50.00);
    });
});

describe('DeleteTransactionTool', function () {
    it('deletes transaction', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    });

    it('updates account balance after deletion', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 100.00,
        ]);

        $balanceAfterCreate = $this->account->refresh()->balance;

        $response = BokkuServer::actingAs($this->user)->tool(DeleteTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk();

        $this->account->refresh();
        expect($this->account->balance)->toBe($balanceAfterCreate + 100.00);
    });

    it('cannot delete other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(DeleteTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    });

    it('returns error for non-existent transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeleteTransactionTool::class, [
            'id' => 99999,
        ]);

        $response->assertHasErrors();
    });
});

describe('ReconcileTransactionTool', function () {
    it('marks transaction as reconciled', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ReconcileTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_reconciled' => true,
        ]);
    });

    it('is idempotent', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => true,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ReconcileTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_reconciled' => true,
        ]);
    });

    it('cannot reconcile other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'is_reconciled' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ReconcileTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_reconciled' => false,
        ]);
    });

    it('can unreconcile a transaction', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => true,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ReconcileTransactionTool::class, [
            'id' => $transaction->id,
            'is_reconciled' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_reconciled' => false,
        ]);
    });
});

describe('BulkReconcileTool', function () {
    it('reconciles multiple transactions', function () {
        $transactions = Transaction::factory()->count(3)->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(BulkReconcileTool::class, [
            'transaction_ids' => $transactions->pluck('id')->toArray(),
        ]);

        $response->assertOk();

        foreach ($transactions as $transaction) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transaction->id,
                'is_reconciled' => true,
            ]);
        }
    });

    it('only reconciles user transactions', function () {
        $userTransaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => false,
        ]);
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $otherTransaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'is_reconciled' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(BulkReconcileTool::class, [
            'transaction_ids' => [$userTransaction->id, $otherTransaction->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $userTransaction->id,
            'is_reconciled' => true,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $otherTransaction->id,
            'is_reconciled' => false,
        ]);
    });

    it('can bulk unreconcile transactions', function () {
        $transactions = Transaction::factory()->count(3)->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => true,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(BulkReconcileTool::class, [
            'transaction_ids' => $transactions->pluck('id')->toArray(),
            'is_reconciled' => false,
        ]);

        $response->assertOk();

        foreach ($transactions as $transaction) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transaction->id,
                'is_reconciled' => false,
            ]);
        }
    });

    it('returns count of reconciled transactions', function () {
        $transactions = Transaction::factory()->count(5)->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'is_reconciled' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(BulkReconcileTool::class, [
            'transaction_ids' => $transactions->pluck('id')->toArray(),
        ]);

        $response->assertOk()
            ->assertSee('5');
    });
});

describe('CreateTransactionTool with tags', function () {
    it('can create transaction with tags', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 50.00,
            'description' => 'Grocery shopping',
            'date' => now()->toDateString(),
            'tags' => ['groceries', 'essential'],
        ]);

        $response->assertOk();

        $transaction = Transaction::where('description', 'Grocery shopping')->first();
        expect($transaction->getUserTags()->pluck('name')->toArray())
            ->toBe(['groceries', 'essential']);
    });

    it('creates transaction without tags when not provided', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 50.00,
            'description' => 'Test transaction',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk();

        $transaction = Transaction::where('description', 'Test transaction')->first();
        expect($transaction->getUserTags()->count())->toBe(0);
    });

    it('validates tags is array', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 50.00,
            'description' => 'Test',
            'date' => now()->toDateString(),
            'tags' => 'not-an-array',
        ]);

        $response->assertHasErrors();
    });

    it('scopes tags to user', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 50.00,
            'description' => 'Tagged transaction',
            'date' => now()->toDateString(),
            'tags' => ['user-tag'],
        ]);

        $response->assertOk();

        $transaction = Transaction::where('description', 'Tagged transaction')->first();
        $tag = $transaction->tags->first();
        expect($tag->type)->toBe('user_'.$this->user->id);
    });

    it('returns tags in create response', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionTool::class, [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'amount' => 50.00,
            'description' => 'Test',
            'date' => now()->toDateString(),
            'tags' => ['tag1', 'tag2'],
        ]);

        $response->assertOk()
            ->assertSee('tag1')
            ->assertSee('tag2');
    });
});

describe('UpdateTransactionTool with tags', function () {
    it('can add tags to existing transaction', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        expect($transaction->getUserTags()->count())->toBe(0);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'tags' => ['new-tag', 'another-tag'],
        ]);

        $response->assertOk();

        $transaction->refresh();
        expect($transaction->getUserTags()->pluck('name')->toArray())
            ->toBe(['new-tag', 'another-tag']);
    });

    it('can replace tags on existing transaction', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);
        $transaction->syncUserTags(['old-tag', 'outdated']);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'tags' => ['new-tag', 'updated'],
        ]);

        $response->assertOk();

        $transaction->refresh();
        expect($transaction->getUserTags()->pluck('name')->toArray())
            ->toBe(['new-tag', 'updated'])
            ->not->toContain('old-tag')
            ->not->toContain('outdated');
    });

    it('can remove all tags', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);
        $transaction->syncUserTags(['tag1', 'tag2']);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'tags' => [],
        ]);

        $response->assertOk();

        $transaction->refresh();
        expect($transaction->getUserTags()->count())->toBe(0);
    });

    it('scopes updated tags to user', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'tags' => ['scoped-tag'],
        ]);

        $response->assertOk();

        $transaction->refresh();
        $tag = $transaction->tags->first();
        expect($tag->type)->toBe('user_'.$this->user->id);
    });

    it('returns tags in update response', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateTransactionTool::class, [
            'id' => $transaction->id,
            'tags' => ['updated-tag'],
        ]);

        $response->assertOk()
            ->assertSee('updated-tag');
    });
});

describe('GetTransactionTool with tags', function () {
    it('returns tags in response', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);
        $transaction->syncUserTags(['important', 'recurring']);

        $response = BokkuServer::actingAs($this->user)->tool(GetTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk()
            ->assertSee('important')
            ->assertSee('recurring');
    });

    it('returns empty array when no tags', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetTransactionTool::class, [
            'id' => $transaction->id,
        ]);

        $response->assertOk();
    });
});

describe('ListTransactionsTool with tags', function () {
    it('filters by single tag', function () {
        $tagged = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Tagged transaction',
        ]);
        $tagged->syncUserTags(['groceries']);

        $untagged = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Untagged transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'tags' => ['groceries'],
        ]);

        $response->assertOk()
            ->assertSee('Tagged transaction')
            ->assertDontSee('Untagged transaction');
    });

    it('filters by multiple tags (OR logic)', function () {
        $groceries = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Groceries',
        ]);
        $groceries->syncUserTags(['groceries']);

        $bills = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Bills',
        ]);
        $bills->syncUserTags(['bills']);

        $other = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Other',
        ]);
        $other->syncUserTags(['entertainment']);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'tags' => ['groceries', 'bills'],
        ]);

        $response->assertOk()
            ->assertSee('Groceries')
            ->assertSee('Bills')
            ->assertDontSee('Other');
    });

    it('returns tags in list response', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Tagged expense',
        ]);
        $transaction->syncUserTags(['tag1', 'tag2']);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class);

        $response->assertOk()
            ->assertSee('tag1')
            ->assertSee('tag2');
    });

    it('does not return other users tags', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $otherTransaction = Transaction::factory()->expense()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'description' => 'Other user transaction',
        ]);
        $otherTransaction->syncUserTags(['shared-tag-name']);

        $userTransaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'User transaction',
        ]);
        $userTransaction->syncUserTags(['user-specific']);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionsTool::class, [
            'tags' => ['shared-tag-name'],
        ]);

        $response->assertOk()
            ->assertDontSee('Other user transaction');
    });
});
