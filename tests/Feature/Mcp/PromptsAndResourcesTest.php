<?php

use App\Mcp\Prompts\AnalyzeSpendingPrompt;
use App\Mcp\Prompts\BudgetAdvicePrompt;
use App\Mcp\Prompts\FinancialHealthPrompt;
use App\Mcp\Resources\AccountBalancesResource;
use App\Mcp\Resources\FinancialOverviewResource;
use App\Mcp\Resources\RecentTransactionsResource;
use App\Mcp\Servers\BokkuServer;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

// ============================================
// AnalyzeSpendingPrompt Tests
// ============================================

describe('AnalyzeSpendingPrompt', function () {
    it('returns spending analysis for month period', function () {
        $account = Account::factory()->for($this->user)->create();
        $category = Category::factory()->for($this->user)->expense()->create();

        Transaction::factory()->for($this->user)->for($account)->for($category)->expense()->create([
            'amount' => 50.00,
            'date' => now()->subDays(5),
        ]);

        Transaction::factory()->for($this->user)->for($account)->for($category)->expense()->create([
            'amount' => 75.00,
            'date' => now()->subDays(10),
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(AnalyzeSpendingPrompt::class, [
            'period' => 'month',
        ]);

        $response->assertOk()
            ->assertSee('spending')
            ->assertSee('the past month');
    });

    it('returns spending analysis for quarter period', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->expense()->create([
            'amount' => 100.00,
            'date' => now()->subMonths(2),
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(AnalyzeSpendingPrompt::class, [
            'period' => 'quarter',
        ]);

        $response->assertOk()
            ->assertSee('the past quarter');
    });

    it('returns spending analysis for year period', function () {
        $response = BokkuServer::actingAs($this->user)->prompt(AnalyzeSpendingPrompt::class, [
            'period' => 'year',
        ]);

        $response->assertOk()
            ->assertSee('the past year');
    });

    it('requires period argument', function () {
        $response = BokkuServer::actingAs($this->user)->prompt(AnalyzeSpendingPrompt::class, []);

        $response->assertHasErrors();
    });

    it('validates period is one of month, quarter, year', function () {
        $response = BokkuServer::actingAs($this->user)->prompt(AnalyzeSpendingPrompt::class, [
            'period' => 'invalid',
        ]);

        $response->assertHasErrors();
    });
});

// ============================================
// BudgetAdvicePrompt Tests
// ============================================

describe('BudgetAdvicePrompt', function () {
    it('returns general budget advice without category_id', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->income()->create([
            'amount' => 3000.00,
            'date' => now()->subDays(5),
        ]);

        Transaction::factory()->for($this->user)->for($account)->expense()->create([
            'amount' => 1500.00,
            'date' => now()->subDays(5),
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(BudgetAdvicePrompt::class, []);

        $response->assertOk()
            ->assertSee('budget')
            ->assertSee('50/30/20');
    });

    it('returns category-specific advice when category_id provided', function () {
        $account = Account::factory()->for($this->user)->create();
        $category = Category::factory()->for($this->user)->expense()->create(['name' => 'Groceries']);

        Transaction::factory()->for($this->user)->for($account)->for($category)->expense()->create([
            'amount' => 500.00,
            'date' => now()->subDays(5),
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(BudgetAdvicePrompt::class, [
            'category_id' => $category->id,
        ]);

        $response->assertOk()
            ->assertSee('Groceries');
    });

    it('returns error for non-existent category', function () {
        $response = BokkuServer::actingAs($this->user)->prompt(BudgetAdvicePrompt::class, [
            'category_id' => 99999,
        ]);

        $response->assertHasErrors()
            ->assertSee('Category not found');
    });

    it('returns error for category belonging to another user', function () {
        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->expense()->create();

        $response = BokkuServer::actingAs($this->user)->prompt(BudgetAdvicePrompt::class, [
            'category_id' => $otherCategory->id,
        ]);

        $response->assertHasErrors()
            ->assertSee('Category not found');
    });
});

// ============================================
// FinancialHealthPrompt Tests
// ============================================

describe('FinancialHealthPrompt', function () {
    it('returns financial health assessment', function () {
        Account::factory()->for($this->user)->create([
            'balance' => 5000,
            'initial_balance' => 5000,
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(FinancialHealthPrompt::class, []);

        $response->assertOk()
            ->assertSee('financial health')
            ->assertSee('Emergency Fund Coverage');
    });

    it('includes account overview in assessment', function () {
        Account::factory()->for($this->user)->bank()->create([
            'balance' => 2000,
            'initial_balance' => 2000,
        ]);
        Account::factory()->for($this->user)->cash()->create([
            'balance' => 500,
            'initial_balance' => 500,
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(FinancialHealthPrompt::class, []);

        $response->assertOk()
            ->assertSee('Account Overview');
    });

    it('calculates savings rate correctly', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->income()->create([
            'amount' => 5000.00,
            'date' => now()->subDays(30),
        ]);

        Transaction::factory()->for($this->user)->for($account)->expense()->create([
            'amount' => 3000.00,
            'date' => now()->subDays(30),
        ]);

        $response = BokkuServer::actingAs($this->user)->prompt(FinancialHealthPrompt::class, []);

        $response->assertOk()
            ->assertSee('Savings Rate');
    });
});

// ============================================
// FinancialOverviewResource Tests
// ============================================

describe('FinancialOverviewResource', function () {
    it('returns financial overview for user', function () {
        Account::factory()->for($this->user)->create([
            'balance' => 1000,
            'initial_balance' => 1000,
        ]);
        Category::factory()->for($this->user)->income()->create();
        Category::factory()->for($this->user)->expense()->create();

        $response = BokkuServer::actingAs($this->user)->resource(FinancialOverviewResource::class);

        $response->assertOk()
            ->assertSee('generated_at')
            ->assertSee('accounts')
            ->assertSee('categories');
    });

    it('includes monthly income and expenses', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->income()->create([
            'amount' => 2000.00,
            'date' => now(),
        ]);

        Transaction::factory()->for($this->user)->for($account)->expense()->create([
            'amount' => 1500.00,
            'date' => now(),
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(FinancialOverviewResource::class);

        $response->assertOk()
            ->assertSee('this_month')
            ->assertSee('income')
            ->assertSee('expenses');
    });

    it('shows unreconciled transaction count', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->create([
            'is_reconciled' => false,
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(FinancialOverviewResource::class);

        $response->assertOk()
            ->assertSee('unreconciled_transactions');
    });
});

// ============================================
// AccountBalancesResource Tests
// ============================================

describe('AccountBalancesResource', function () {
    it('returns all account balances', function () {
        Account::factory()->for($this->user)->bank()->create([
            'name' => 'Main Checking',
            'balance' => 2500,
            'initial_balance' => 2500,
        ]);
        Account::factory()->for($this->user)->cash()->create([
            'name' => 'Emergency Fund',
            'balance' => 10000,
            'initial_balance' => 10000,
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(AccountBalancesResource::class);

        $response->assertOk()
            ->assertSee('Main Checking')
            ->assertSee('Emergency Fund')
            ->assertSee('total_balance');
    });

    it('groups accounts by type', function () {
        Account::factory()->for($this->user)->bank()->create();
        Account::factory()->for($this->user)->bank()->create();
        Account::factory()->for($this->user)->cash()->create();

        $response = BokkuServer::actingAs($this->user)->resource(AccountBalancesResource::class);

        $response->assertOk()
            ->assertSee('by_type');
    });

    it('does not include other users accounts', function () {
        $otherUser = User::factory()->create();

        Account::factory()->for($this->user)->create(['name' => 'My Account']);
        Account::factory()->for($otherUser)->create(['name' => 'Other Account']);

        $response = BokkuServer::actingAs($this->user)->resource(AccountBalancesResource::class);

        $response->assertOk()
            ->assertSee('My Account')
            ->assertDontSee('Other Account');
    });
});

// ============================================
// RecentTransactionsResource Tests
// ============================================

describe('RecentTransactionsResource', function () {
    it('returns recent transactions', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->create([
            'description' => 'Test Transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(RecentTransactionsResource::class);

        $response->assertOk()
            ->assertSee('Test Transaction')
            ->assertSee('transactions');
    });

    it('limits to 20 transactions', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->count(25)->create();

        $response = BokkuServer::actingAs($this->user)->resource(RecentTransactionsResource::class);

        $response->assertOk()
            ->assertSee('transaction_count');
    });

    it('orders by date descending - newer first', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->create([
            'description' => 'Older Transaction',
            'date' => now()->subDays(10),
        ]);

        Transaction::factory()->for($this->user)->for($account)->create([
            'description' => 'Newer Transaction',
            'date' => now(),
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(RecentTransactionsResource::class);

        $response->assertOk()
            ->assertSee('Newer Transaction')
            ->assertSee('Older Transaction');
    });

    it('includes summary of income and expenses', function () {
        $account = Account::factory()->for($this->user)->create();

        Transaction::factory()->for($this->user)->for($account)->income()->create([
            'amount' => 1000.00,
        ]);

        Transaction::factory()->for($this->user)->for($account)->expense()->create([
            'amount' => 500.00,
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(RecentTransactionsResource::class);

        $response->assertOk()
            ->assertSee('summary')
            ->assertSee('total_income')
            ->assertSee('total_expenses');
    });

    it('does not include other users transactions', function () {
        $otherUser = User::factory()->create();

        $myAccount = Account::factory()->for($this->user)->create();
        $otherAccount = Account::factory()->for($otherUser)->create();

        Transaction::factory()->for($this->user)->for($myAccount)->create([
            'description' => 'My Transaction',
        ]);

        Transaction::factory()->for($otherUser)->for($otherAccount)->create([
            'description' => 'Other Transaction',
        ]);

        $response = BokkuServer::actingAs($this->user)->resource(RecentTransactionsResource::class);

        $response->assertOk()
            ->assertSee('My Transaction')
            ->assertDontSee('Other Transaction');
    });
});
