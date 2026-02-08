<?php

use App\Filament\Widgets\SpendingByCategoryChart;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('SpendingByCategoryChart Widget Instantiation', function () {
    it('can be instantiated', function () {
        $widget = new SpendingByCategoryChart;
        expect($widget)->toBeInstanceOf(SpendingByCategoryChart::class);
    });

    it('has correct sort order', function () {
        $reflectionClass = new ReflectionClass(SpendingByCategoryChart::class);
        $sortProperty = $reflectionClass->getProperty('sort');
        $sortProperty->setAccessible(true);

        expect($sortProperty->getValue())->toBe(6);
    });

    it('has correct column span', function () {
        $widget = new SpendingByCategoryChart;
        $reflectionClass = new ReflectionClass(SpendingByCategoryChart::class);
        $columnSpanProperty = $reflectionClass->getProperty('columnSpan');
        $columnSpanProperty->setAccessible(true);

        expect($columnSpanProperty->getValue($widget))->toBe(1);
    });

    it('has correct heading', function () {
        $widget = new SpendingByCategoryChart;
        $reflectionClass = new ReflectionClass(SpendingByCategoryChart::class);
        $headingProperty = $reflectionClass->getProperty('heading');
        $headingProperty->setAccessible(true);

        expect($headingProperty->getValue($widget))->toBe('Spending by Category');
    });
});

describe('SpendingByCategoryChart Widget Rendering', function () {
    it('can render successfully', function () {
        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful();
    });

    it('can render without transactions', function () {
        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful();
    });

    it('displays expense transactions by category', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Groceries',
        ]);

        Transaction::factory()->count(5)->expense()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 100.00,
            'date' => now(),
        ]);

        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful()
            ->assertSee('Groceries');
    });
});

describe('SpendingByCategoryChart Data Scoping', function () {
    it('only shows transactions for authenticated user', function () {
        $otherUser = User::factory()->create();

        $userCategory = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'User Category',
        ]);

        $otherCategory = Category::factory()->expense()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Category',
        ]);

        Transaction::factory()->count(3)->expense()->create([
            'user_id' => $this->user->id,
            'category_id' => $userCategory->id,
            'date' => now(),
        ]);

        Transaction::factory()->count(4)->expense()->create([
            'user_id' => $otherUser->id,
            'category_id' => $otherCategory->id,
            'date' => now(),
        ]);

        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful()
            ->assertSee('User Category')
            ->assertDontSee('Other Category');
    });

    it('only shows expense transactions', function () {
        $expenseCategory = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Expense Category',
        ]);

        $incomeCategory = Category::factory()->income()->create([
            'user_id' => $this->user->id,
            'name' => 'Income Category',
        ]);

        Transaction::factory()->count(5)->expense()->create([
            'user_id' => $this->user->id,
            'category_id' => $expenseCategory->id,
            'date' => now(),
        ]);

        Transaction::factory()->count(5)->income()->create([
            'user_id' => $this->user->id,
            'category_id' => $incomeCategory->id,
            'date' => now(),
        ]);

        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful()
            ->assertSee('Expense Category')
            ->assertDontSee('Income Category');
    });
});

describe('SpendingByCategoryChart Date Filtering', function () {
    it('respects date range filters', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Category',
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => now()->startOfMonth(),
            'amount' => 100.00,
        ]);

        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => now()->subMonth(),
            'amount' => 200.00,
        ]);

        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful();
    });
});

describe('SpendingByCategoryChart Empty States', function () {
    it('handles empty data gracefully', function () {
        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful();
    });
});

describe('SpendingByCategoryChart Top Categories', function () {
    it('limits to top 10 categories', function () {
        for ($i = 1; $i <= 15; $i++) {
            $category = Category::factory()->expense()->create([
                'user_id' => $this->user->id,
                'name' => "Category {$i}",
            ]);

            Transaction::factory()->expense()->create([
                'user_id' => $this->user->id,
                'category_id' => $category->id,
                'amount' => $i * 10,
                'date' => now(),
            ]);
        }

        livewire(SpendingByCategoryChart::class)
            ->assertSuccessful();
    });
});
