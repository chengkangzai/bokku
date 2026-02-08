<?php

use App\Filament\Widgets\SpendingByTagsChart;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render successfully when user has tagged transactions', function () {
    $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $transaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'date' => now(),
    ]);

    $transaction->attachTag('essential', 'user_'.$this->user->id);

    livewire(SpendingByTagsChart::class)
        ->assertSuccessful();
});

it('is hidden when user has no tagged transactions', function () {
    expect(SpendingByTagsChart::canView())->toBeFalse();
});

it('only shows expense transactions', function () {
    $expenseCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $incomeCategory = Category::factory()->income()->create(['user_id' => $this->user->id]);

    $expenseTransaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $expenseCategory->id,
        'date' => now(),
    ]);

    $incomeTransaction = Transaction::factory()->income()->create([
        'user_id' => $this->user->id,
        'category_id' => $incomeCategory->id,
        'date' => now(),
    ]);

    $expenseTransaction->attachTag('essential', 'user_'.$this->user->id);
    $incomeTransaction->attachTag('salary', 'user_'.$this->user->id);

    livewire(SpendingByTagsChart::class)
        ->assertSuccessful()
        ->assertSee('essential')
        ->assertDontSee('salary');
});

it('only shows transactions for authenticated user', function () {
    $otherUser = User::factory()->create();

    $userCategory = Category::factory()->expense()->create(['user_id' => $this->user->id]);
    $otherCategory = Category::factory()->expense()->create(['user_id' => $otherUser->id]);

    $userTransaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'category_id' => $userCategory->id,
        'date' => now(),
    ]);

    $otherTransaction = Transaction::factory()->expense()->create([
        'user_id' => $otherUser->id,
        'category_id' => $otherCategory->id,
        'date' => now(),
    ]);

    $userTransaction->attachTag('user-tag', 'user_'.$this->user->id);
    $otherTransaction->attachTag('other-tag', 'user_'.$otherUser->id);

    livewire(SpendingByTagsChart::class)
        ->assertSuccessful()
        ->assertSee('user-tag')
        ->assertDontSee('other-tag');
});
