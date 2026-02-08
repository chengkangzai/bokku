<?php

use App\Filament\Widgets\SpendingByCategoryTable;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render successfully', function () {
    livewire(SpendingByCategoryTable::class)
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

    livewire(SpendingByCategoryTable::class)
        ->assertSuccessful()
        ->assertSee('Groceries')
        ->assertSee('MYR');
});

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

    livewire(SpendingByCategoryTable::class)
        ->assertSuccessful()
        ->assertSee('User Category')
        ->assertDontSee('Other Category');
});

it('handles empty data gracefully', function () {
    livewire(SpendingByCategoryTable::class)
        ->assertSuccessful();
});
