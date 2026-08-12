<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpendingAnalysisService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = new SpendingAnalysisService;
    $this->month = CarbonImmutable::now()->startOfMonth();
});

describe('summary', function () {
    it('totals the month and computes deltas against the previous month', function () {
        Transaction::factory()->income()->withAmount(1000.00)->create([
            'user_id' => $this->user->id,
            'date' => $this->month,
        ]);
        Transaction::factory()->expense()->withAmount(400.00)->create([
            'user_id' => $this->user->id,
            'date' => $this->month,
        ]);
        Transaction::factory()->expense()->withAmount(800.00)->create([
            'user_id' => $this->user->id,
            'date' => $this->month->subMonth(),
        ]);

        $summary = $this->service->summary($this->user->id, $this->month);

        expect($summary['income'])->toBe(1000.0)
            ->and($summary['expense'])->toBe(400.0)
            ->and($summary['net'])->toBe(600.0)
            ->and($summary['expense_delta'])->toBe(-50.0)
            ->and($summary['income_delta'])->toBeNull();
    });

    it('ignores other users', function () {
        Transaction::factory()->income()->withAmount(999.00)->create([
            'date' => $this->month,
        ]);

        $summary = $this->service->summary($this->user->id, $this->month);

        expect($summary['income'])->toBe(0.0);
    });
});

describe('breakdown', function () {
    it('includes uncategorized spending', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        Transaction::factory()->expense()->withAmount(300.00)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => $this->month,
        ]);
        Transaction::factory()->expense()->withAmount(120.00)->create([
            'user_id' => $this->user->id,
            'category_id' => null,
            'date' => $this->month,
        ]);

        $breakdown = $this->service->breakdown($this->user->id, $this->month);

        expect($breakdown)->toHaveCount(2)
            ->and($breakdown->pluck('name')->all())->toContain('Uncategorized')
            ->and($breakdown->firstWhere('name', 'Uncategorized')->total)->toBe(120.0);
    });

    it('attaches previous month totals per category', function () {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        Transaction::factory()->expense()->withAmount(200.00)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => $this->month,
        ]);
        Transaction::factory()->expense()->withAmount(500.00)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => $this->month->subMonth(),
        ]);

        $row = $this->service->breakdown($this->user->id, $this->month)->first();

        expect($row->total)->toBe(200.0)
            ->and($row->previous)->toBe(500.0);
    });
});

describe('topMovers', function () {
    it('ranks categories by absolute change including disappeared ones', function () {
        $food = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Food']);
        $travel = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Travel']);

        Transaction::factory()->expense()->withAmount(100.00)->create([
            'user_id' => $this->user->id, 'category_id' => $food->id, 'date' => $this->month,
        ]);
        Transaction::factory()->expense()->withAmount(700.00)->create([
            'user_id' => $this->user->id, 'category_id' => $food->id, 'date' => $this->month->subMonth(),
        ]);
        Transaction::factory()->expense()->withAmount(50.00)->create([
            'user_id' => $this->user->id, 'category_id' => $travel->id, 'date' => $this->month->subMonth(),
        ]);

        $movers = $this->service->topMovers($this->user->id, $this->month);

        expect($movers[0]['name'])->toBe('Food')
            ->and($movers[0]['change'])->toBe(-600.0)
            ->and($movers[0]['percent'])->toBe(-85.7)
            ->and($movers[1]['name'])->toBe('Travel')
            ->and($movers[1]['change'])->toBe(-50.0);
    });
});

describe('trends', function () {
    it('builds monthly series and averages completed months only', function () {
        Transaction::factory()->expense()->withAmount(300.00)->create([
            'user_id' => $this->user->id,
            'date' => $this->month->subMonth(),
        ]);
        Transaction::factory()->expense()->withAmount(100.00)->create([
            'user_id' => $this->user->id,
            'date' => $this->month,
        ]);
        Transaction::factory()->income()->withAmount(500.00)->create([
            'user_id' => $this->user->id,
            'date' => $this->month,
        ]);

        $trends = $this->service->trends($this->user->id, $this->month, 3);

        expect($trends['labels'])->toHaveCount(3)
            ->and(end($trends['expense']))->toBe(100.0)
            ->and(end($trends['net']))->toBe(400.0)
            ->and($trends['average_expense'])->toBe(150.0); // (0 + 300) / 2 completed months
    });
});

describe('tags', function () {
    it('detects tagged transactions and breaks down spending by tag', function () {
        expect($this->service->hasTaggedTransactions($this->user->id))->toBeFalse();

        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $transaction = Transaction::factory()->expense()->withAmount(80.00)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'date' => $this->month,
        ]);
        $transaction->attachTag('holiday', "user_{$this->user->id}");

        expect($this->service->hasTaggedTransactions($this->user->id))->toBeTrue();

        $tags = $this->service->tagBreakdown($this->user->id, $this->month);

        expect($tags)->toHaveCount(1)
            ->and($tags->first()->name)->toBe('holiday')
            ->and($tags->first()->total)->toBe(80.0)
            ->and($tags->first()->color)->toBe(Category::DEFAULT_COLORS[0]);
    });
});
