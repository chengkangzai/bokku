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

it('hides tag widgets when the user has no tagged transactions', function () {
    $html = $this->get(SpendingAnalysis::getUrl())->getContent();

    expect(substr_count($html, 'wire:name="App\Filament\Widgets\SpendingByTagsChart"'))->toBe(0)
        ->and(substr_count($html, 'wire:name="App\Filament\Widgets\SpendingByTagsTable"'))->toBe(0);
});

it('shows tag widgets when the user has tagged transactions', function () {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
    $transaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);
    $transaction->attachTag('holiday', 'user_'.$this->user->id);

    $html = $this->get(SpendingAnalysis::getUrl())->getContent();

    expect(substr_count($html, 'wire:name="App\Filament\Widgets\SpendingByTagsChart"'))->toBe(1)
        ->and(substr_count($html, 'wire:name="App\Filament\Widgets\SpendingByTagsTable"'))->toBe(1);
});

it('renders each widget only once', function () {
    $html = $this->get(SpendingAnalysis::getUrl())->getContent();

    expect(substr_count($html, 'wire:name="App\Filament\Widgets\TopExpensesWidget"'))->toBe(1)
        ->and(substr_count($html, 'wire:name="App\Filament\Widgets\IncomeSourcesWidget"'))->toBe(1)
        ->and(substr_count($html, 'wire:name="App\Filament\Widgets\SpendingTrendsChart"'))->toBe(1);
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
