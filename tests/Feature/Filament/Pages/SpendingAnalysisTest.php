<?php

use App\Filament\Pages\SpendingAnalysis;
use App\Filament\Widgets\AccountBalancesWidget;
use App\Filament\Widgets\IncomeSourcesWidget;
use App\Filament\Widgets\SmartInsightsWidget;
use App\Filament\Widgets\SpendingStatsOverview;
use App\Filament\Widgets\TopExpensesWidget;
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

it('only accessible by authenticated users', function () {
    auth()->logout();

    $this->get(SpendingAnalysis::getUrl())
        ->assertRedirect();
});

describe('SpendingStatsOverview', function () {
    it('renders stats overview widget', function () {
        Transaction::factory()->income()->withAmount(5000)->create([
            'user_id' => $this->user->id,
            'date' => now(),
        ]);

        Transaction::factory()->expense()->withAmount(2000)->create([
            'user_id' => $this->user->id,
            'date' => now(),
        ]);

        livewire(SpendingStatsOverview::class)
            ->assertSuccessful()
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

        livewire(SpendingStatsOverview::class)
            ->assertSuccessful()
            ->assertSee('40%');
    });

    it('returns zero savings rate when there is no income', function () {
        Transaction::factory()->expense()->withAmount(1000)->create([
            'user_id' => $this->user->id,
            'date' => now(),
        ]);

        livewire(SpendingStatsOverview::class)
            ->assertSuccessful()
            ->assertSee('0%');
    });
});

describe('AccountBalancesWidget', function () {
    it('displays account balances', function () {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Account',
            'balance' => 100000,
        ]);

        livewire(AccountBalancesWidget::class)
            ->assertSuccessful()
            ->assertSee('Test Account');
    });

    it('only shows user-specific data', function () {
        $otherUser = User::factory()->create();

        Account::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Account',
        ]);

        livewire(AccountBalancesWidget::class)
            ->assertDontSee('Other User Account');
    });
});

describe('TopExpensesWidget', function () {
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

        livewire(TopExpensesWidget::class)
            ->assertSuccessful()
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

        $widget = livewire(TopExpensesWidget::class)->instance();
        $expenses = $widget->getTableRecords();

        expect($expenses)->toHaveCount(5);
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

        $widget = livewire(TopExpensesWidget::class)->instance();
        $expenses = $widget->getTableRecords();

        expect($expenses->first()->percentage)->toBe(70.0);
        expect($expenses->last()->percentage)->toBe(30.0);
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

        $widget = livewire(TopExpensesWidget::class)->instance();
        $expenses = $widget->getTableRecords();

        expect($expenses)->toHaveCount(1);
        expect($expenses->first()->total)->toBe(10000);
    });
});

describe('IncomeSourcesWidget', function () {
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

        livewire(IncomeSourcesWidget::class)
            ->assertSuccessful()
            ->assertSee('Salary');
    });
});

describe('SmartInsightsWidget', function () {
    it('generates smart insights', function () {
        $widget = livewire(SmartInsightsWidget::class)->instance();
        $insights = $widget->getInsights();

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

        $widget = livewire(SmartInsightsWidget::class)->instance();
        $insights = $widget->getInsights();

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

        $widget = livewire(SmartInsightsWidget::class)->instance();
        $insights = $widget->getInsights();

        $hasSuccess = collect($insights)->contains(fn ($insight) => $insight['type'] === 'success');

        expect($hasSuccess)->toBeTrue();
    });
});
