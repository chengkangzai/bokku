<?php

use App\Enums\AccountType;
use App\Filament\Pages\SpendingAnalysis;
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
});

it('can render the spending analysis page', function () {
    livewire(SpendingAnalysis::class)
        ->assertSuccessful();
});

it('has correct navigation properties', function () {
    $reflectionClass = new ReflectionClass(SpendingAnalysis::class);

    $iconProperty = $reflectionClass->getProperty('navigationIcon');
    $iconProperty->setAccessible(true);
    expect($iconProperty->getValue())->toBe('heroicon-o-chart-pie');

    $labelProperty = $reflectionClass->getProperty('navigationLabel');
    $labelProperty->setAccessible(true);
    expect($labelProperty->getValue())->toBe('Spending Analysis');

    $sortProperty = $reflectionClass->getProperty('navigationSort');
    $sortProperty->setAccessible(true);
    expect($sortProperty->getValue())->toBe(2);
});

it('displays all spending widgets', function () {
    livewire(SpendingAnalysis::class)
        ->assertSuccessful();
});

it('only accessible by authenticated users', function () {
    auth()->logout();

    $this->get(SpendingAnalysis::getUrl())
        ->assertRedirect();
});

it('displays summary metrics correctly', function () {
    Transaction::factory()->income()->withAmount(5000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    Transaction::factory()->expense()->withAmount(2000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    livewire(SpendingAnalysis::class)
        ->assertSee('Total Balance')
        ->assertSee('Monthly Income')
        ->assertSee('Monthly Expenses')
        ->assertSee('Savings Rate');
});

it('calculates savings rate correctly', function () {
    Transaction::factory()->income()->withAmount(5000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    Transaction::factory()->expense()->withAmount(3000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $metrics = $component->getSummaryMetrics();

    expect($metrics['savings_rate'])->toBe(40.0);
});

it('returns zero savings rate when there is no income', function () {
    Transaction::factory()->expense()->withAmount(1000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $metrics = $component->getSummaryMetrics();

    expect($metrics['savings_rate'])->toBe(0.0);
});

it('displays account balances', function () {
    Account::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Account',
        'balance' => 100000,
    ]);

    livewire(SpendingAnalysis::class)
        ->assertSee('Test Account')
        ->assertSee('Account Balances');
});

it('shows loan section when loans exist', function () {
    Account::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Car Loan',
        'type' => AccountType::Loan,
        'balance' => -50000,
    ]);

    livewire(SpendingAnalysis::class)
        ->assertSee('Loan Progress')
        ->assertSee('Car Loan');
});

it('hides loan section when no loans exist', function () {
    Account::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Bank Account',
        'type' => AccountType::Bank,
    ]);

    livewire(SpendingAnalysis::class)
        ->assertDontSee('Loan Progress');
});

it('displays top expense categories', function () {
    $category = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Groceries',
    ]);

    Transaction::factory()->count(5)->expense()->withAmount(100)->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now(),
    ]);

    livewire(SpendingAnalysis::class)
        ->assertSee('Top Expenses')
        ->assertSee('Groceries');
});

it('limits top expenses to 5 categories', function () {
    for ($i = 1; $i <= 7; $i++) {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => "Category {$i}",
        ]);

        Transaction::factory()->expense()->withAmount($i * 100)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => now(),
        ]);
    }

    $component = livewire(SpendingAnalysis::class)->instance();
    $expenses = $component->getTopExpenseCategories();

    expect($expenses)->toHaveCount(5);
});

it('displays income sources', function () {
    $category = Category::factory()->income()->create([
        'user_id' => $this->user->id,
        'name' => 'Salary',
    ]);

    Transaction::factory()->income()->withAmount(5000)->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now(),
    ]);

    livewire(SpendingAnalysis::class)
        ->assertSee('Income Sources')
        ->assertSee('Salary');
});

it('generates smart insights', function () {
    $component = livewire(SpendingAnalysis::class)->instance();
    $insights = $component->getSmartInsights();

    expect($insights)->toBeArray()
        ->not->toBeEmpty();

    expect($insights[0])
        ->toHaveKeys(['type', 'icon', 'message']);
});

it('shows budget warning insight when budgets are at risk', function () {
    $category = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Dining',
    ]);

    Budget::factory()->monthly()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'amount' => 100,
    ]);

    Transaction::factory()->count(10)->expense()->withAmount(10)->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now(),
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $insights = $component->getSmartInsights();

    $hasWarning = collect($insights)->contains(fn ($insight) => $insight['type'] === 'warning');

    expect($hasWarning)->toBeTrue();
});

it('shows savings achievement insight when savings rate is high', function () {
    Transaction::factory()->income()->withAmount(5000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    Transaction::factory()->expense()->withAmount(1000)->create([
        'user_id' => $this->user->id,
        'date' => now(),
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $insights = $component->getSmartInsights();

    $hasSuccess = collect($insights)->contains(fn ($insight) => $insight['type'] === 'success');

    expect($hasSuccess)->toBeTrue();
});

it('only shows user-specific data', function () {
    $otherUser = User::factory()->create();

    Account::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Other User Account',
    ]);

    livewire(SpendingAnalysis::class)
        ->assertDontSee('Other User Account');
});

it('handles empty state gracefully', function () {
    livewire(SpendingAnalysis::class)
        ->assertSuccessful()
        ->assertSee('No accounts yet')
        ->assertSee('No expenses this month')
        ->assertSee('No income this month');
});

it('calculates expense percentages correctly', function () {
    $category1 = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Dining',
    ]);

    $category2 = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Transport',
    ]);

    Transaction::factory()->expense()->withAmount(700)->create([
        'user_id' => $this->user->id,
        'category_id' => $category1->id,
        'date' => now(),
    ]);

    Transaction::factory()->expense()->withAmount(300)->create([
        'user_id' => $this->user->id,
        'category_id' => $category2->id,
        'date' => now(),
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $expenses = $component->getTopExpenseCategories();

    expect($expenses->first()->percentage)->toBe(70.0);
    expect($expenses->last()->percentage)->toBe(30.0);
});

it('orders accounts by balance descending', function () {
    Account::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Low Balance',
        'balance' => 10000,
    ]);

    Account::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'High Balance',
        'balance' => 100000,
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $accounts = $component->getAccountsData();

    expect($accounts->first()['name'])->toBe('High Balance');
});

it('filters transactions by current month', function () {
    $category = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Groceries',
    ]);

    Transaction::factory()->expense()->withAmount(100)->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now(),
    ]);

    Transaction::factory()->expense()->withAmount(200)->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now()->subMonth(),
    ]);

    $component = livewire(SpendingAnalysis::class)->instance();
    $expenses = $component->getTopExpenseCategories();

    expect($expenses->first()->formatted_total)->toBe('MYR 100.00');
});
