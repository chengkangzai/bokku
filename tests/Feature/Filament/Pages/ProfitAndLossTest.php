<?php

use App\Filament\Pages\ProfitAndLoss;
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

it('can render the profit and loss page', function () {
    livewire(ProfitAndLoss::class)
        ->assertSuccessful();
});

it('lives in the Insights navigation group', function () {
    $reflectionClass = new ReflectionClass(ProfitAndLoss::class);

    expect($reflectionClass->getProperty('navigationGroup')->getValue())->toBe('Insights');
});

it('shows income and expense lines with totals and savings rate', function () {
    $salary = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'income', 'name' => 'Salary']);
    $food = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Food']);

    Transaction::factory()->income()->withAmount(4000.00)->create([
        'user_id' => $this->user->id,
        'category_id' => $salary->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(1000.00)->create([
        'user_id' => $this->user->id,
        'category_id' => $food->id,
        'date' => now()->startOfMonth(),
    ]);

    livewire(ProfitAndLoss::class)
        ->assertSee('Salary')
        ->assertSee('Food')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('filters%5Bcategory_id%5D%5Bvalue%5D='.$food->id)
        ->assertSeeHtml('filters%5Bdate%5D%5Bfrom%5D='.now()->subMonths(3)->startOfMonth()->toDateString())
        ->assertSee('4,000.00')
        ->assertSee('1,000.00')
        ->assertSee('+MYR 3,000.00')
        ->assertSee('75%');
});

it('shows categories that only existed last month', function () {
    $rent = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Rent']);

    Transaction::factory()->expense()->withAmount(550.00)->create([
        'user_id' => $this->user->id,
        'category_id' => $rent->id,
        'date' => now()->subMonth()->startOfMonth(),
    ]);

    livewire(ProfitAndLoss::class)
        ->assertSee('Rent')
        ->assertSee('550.00');
});

it('can exclude company expenses', function () {
    $company = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Company Expenses']);
    $food = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Food']);

    Transaction::factory()->expense()->withAmount(900.00)->create([
        'user_id' => $this->user->id,
        'category_id' => $company->id,
        'date' => now()->startOfMonth(),
    ]);
    Transaction::factory()->expense()->withAmount(100.00)->create([
        'user_id' => $this->user->id,
        'category_id' => $food->id,
        'date' => now()->startOfMonth(),
    ]);

    livewire(ProfitAndLoss::class)
        ->assertSee('Company Expenses')
        ->assertSee('MYR 1,000.00')
        ->set('excludeCompany', true)
        ->assertDontSee('Company Expenses')
        ->assertSee('MYR 100.00');
});

it('navigates months with the pager', function () {
    livewire(ProfitAndLoss::class)
        ->assertSet('month', now()->format('Y-m'))
        ->call('previousMonth')
        ->assertSet('month', now()->subMonth()->format('Y-m'))
        ->call('nextMonth')
        ->assertSet('month', now()->format('Y-m'))
        ->call('nextMonth')
        ->assertSet('month', now()->format('Y-m'));
});
