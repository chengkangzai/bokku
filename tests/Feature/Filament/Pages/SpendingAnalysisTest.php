<?php

use App\Filament\Pages\SpendingAnalysis;
use App\Filament\Widgets\IncomeSourcesWidget;
use App\Filament\Widgets\TopExpensesWidget;
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

it('renders no filament widgets on the page', function () {
    $html = $this->get(SpendingAnalysis::getUrl())->getContent();

    expect(substr_count($html, 'wire:name="App\Filament\Widgets'))->toBe(0);
});

it('defaults to the current month and navigates with the pager', function () {
    livewire(SpendingAnalysis::class)
        ->assertSet('month', now()->format('Y-m'))
        ->call('previousMonth')
        ->assertSet('month', now()->subMonth()->format('Y-m'))
        ->call('nextMonth')
        ->assertSet('month', now()->format('Y-m'))
        ->call('nextMonth')
        ->assertSet('month', now()->format('Y-m'));
});

it('shows the month summary with figures', function () {
    $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

    Transaction::factory()->income()->withAmount(5000.00)->create([
        'user_id' => $this->user->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(1234.56)->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now()->startOfMonth(),
    ]);

    livewire(SpendingAnalysis::class)
        ->assertSee('MYR 5,000.00')
        ->assertSee('MYR 1,234.56')
        ->assertSee($category->name);
});

it('validates the trend months and group by setters', function () {
    $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $transaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);
    $transaction->attachTag('holiday', 'user_'.$this->user->id);

    livewire(SpendingAnalysis::class)
        ->call('setTrendMonths', 12)
        ->assertSet('trendMonths', 12)
        ->call('setTrendMonths', 1)
        ->assertSet('trendMonths', 1)
        ->call('setTrendMonths', 7)
        ->assertSet('trendMonths', 1)
        ->call('setGroupBy', 'tag')
        ->assertSet('groupBy', 'tag')
        ->call('setGroupBy', 'bogus')
        ->assertSet('groupBy', 'tag');
});

it('falls back to category grouping when the user has no tags', function () {
    livewire(SpendingAnalysis::class)
        ->call('setGroupBy', 'tag')
        ->assertSet('groupBy', 'category');
});

it('shows the group-by toggle only when tagged transactions exist', function () {
    livewire(SpendingAnalysis::class)
        ->assertDontSeeHtml('setGroupBy');

    $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $transaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);
    $transaction->attachTag('holiday', 'user_'.$this->user->id);

    livewire(SpendingAnalysis::class)
        ->assertSeeHtml('setGroupBy');
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
