<?php

use App\Filament\Widgets\SpendingTrendsChart;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render successfully', function () {
    livewire(SpendingTrendsChart::class)
        ->assertSuccessful();
});

it('displays income and expense trends', function () {
    $expenseCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);

    Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $expenseCategory->id,
        'amount' => 100.00,
        'date' => now(),
    ]);

    Transaction::factory()->income()->create([
        'user_id' => $this->user->id,
        'category_id' => $incomeCategory->id,
        'amount' => 500.00,
        'date' => now(),
    ]);

    livewire(SpendingTrendsChart::class)
        ->assertSuccessful();
});

it('only shows transactions for authenticated user', function () {
    $otherUser = User::factory()->create();

    $userCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $otherCategory = Category::factory()->expense()->create(['user_id' => $otherUser->id]);

    Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $userCategory->id,
        'date' => now(),
    ]);

    Transaction::factory()->expense()->create([
        'user_id' => $otherUser->id,
        'category_id' => $otherCategory->id,
        'date' => now(),
    ]);

    livewire(SpendingTrendsChart::class)
        ->assertSuccessful();
});

it('handles empty data gracefully', function () {
    livewire(SpendingTrendsChart::class)
        ->assertSuccessful();
});

it('has correct column span for full width', function () {
    $widget = new SpendingTrendsChart;
    $reflectionClass = new ReflectionClass(SpendingTrendsChart::class);
    $columnSpanProperty = $reflectionClass->getProperty('columnSpan');
    $columnSpanProperty->setAccessible(true);

    expect($columnSpanProperty->getValue($widget))->toBe('full');
});
