<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    $this->category = Category::factory()->create(['user_id' => $this->user->id]);
});

describe('RecurringTransaction Model Relationships', function () {
    it('belongs to a user', function () {
        $recurring = RecurringTransaction::factory()->create(['user_id' => $this->user->id]);

        expect($recurring->user)->toBeInstanceOf(User::class);
        expect($recurring->user->id)->toBe($this->user->id);
    });

    it('belongs to an account', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        expect($recurring->account)->toBeInstanceOf(Account::class);
        expect($recurring->account->id)->toBe($this->account->id);
    });

    it('belongs to a category', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        expect($recurring->category)->toBeInstanceOf(Category::class);
        expect($recurring->category->id)->toBe($this->category->id);
    });

    it('has many generated transactions', function () {
        $recurring = RecurringTransaction::factory()->create(['user_id' => $this->user->id]);

        Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'recurring_transaction_id' => $recurring->id,
        ]);

        expect($recurring->generatedTransactions)->toHaveCount(3);
        expect($recurring->generatedTransactions->first())->toBeInstanceOf(Transaction::class);
    });

    it('has a to_account for transfers', function () {
        $toAccount = Account::factory()->create(['user_id' => $this->user->id]);
        $recurring = RecurringTransaction::factory()->transfer()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'to_account_id' => $toAccount->id,
        ]);

        expect($recurring->toAccount)->toBeInstanceOf(Account::class);
        expect($recurring->toAccount->id)->toBe($toAccount->id);
    });
});

describe('RecurringTransaction Date Calculations', function () {
    it('calculates next date for daily frequency', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'daily',
            'interval' => 3,
            'next_date' => Carbon::parse('2024-01-15'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2024-01-18');
    });

    it('calculates next date for weekly frequency without specific day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'weekly',
            'interval' => 2,
            'day_of_week' => null,
            'next_date' => Carbon::parse('2024-01-15'), // Monday
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2024-01-29');
    });

    it('calculates next date for weekly frequency with specific day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'weekly',
            'interval' => 1,
            'day_of_week' => Carbon::FRIDAY,
            'next_date' => Carbon::parse('2024-01-15'), // Monday
        ]);

        $nextDate = $recurring->calculateNextDate();

        // Should be the next Friday after adding 1 week
        expect($nextDate->dayOfWeek)->toBe(Carbon::FRIDAY);
        expect($nextDate->format('Y-m-d'))->toBe('2024-01-26');
    });

    it('calculates next date for monthly frequency with day 15', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 15,
            'next_date' => Carbon::parse('2024-01-15'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2024-02-15');
    });

    it('handles end of month correctly for day 31', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 31,
            'next_date' => Carbon::parse('2024-01-31'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        // February 2024 is a leap year, so last day is 29th
        expect($nextDate->format('Y-m-d'))->toBe('2024-02-29');
    });

    it('handles non-leap year February for day 31', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 31,
            'next_date' => Carbon::parse('2023-01-31'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        // February 2023 is not a leap year, so last day is 28th
        expect($nextDate->format('Y-m-d'))->toBe('2023-02-28');
    });

    it('handles day 30 in February', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 30,
            'next_date' => Carbon::parse('2024-01-30'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        // February only has 29 days in 2024
        expect($nextDate->format('Y-m-d'))->toBe('2024-02-29');
    });

    it('calculates next date for annual frequency', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'annual',
            'interval' => 1,
            'month_of_year' => 6, // June
            'day_of_month' => 15,
            'next_date' => Carbon::parse('2024-06-15'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2025-06-15');
    });

    it('handles leap year for annual frequency on Feb 29', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'annual',
            'interval' => 1,
            'month_of_year' => 2,
            'day_of_month' => 29,
            'next_date' => Carbon::parse('2024-02-29'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        // 2025 is not a leap year, so Feb 29 becomes Feb 28
        expect($nextDate->format('Y-m-d'))->toBe('2025-02-28');
    });

    it('calculates with multi-year interval', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'annual',
            'interval' => 3,
            'next_date' => Carbon::parse('2024-01-15'),
            'month_of_year' => null,  // Ensure no month override
            'day_of_month' => null,   // Ensure no day override
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2027-01-15');
    });
});

describe('RecurringTransaction isDue Logic', function () {
    it('returns true when next_date is today', function () {
        $recurring = RecurringTransaction::factory()->make([
            'next_date' => now()->startOfDay(),
            'is_active' => true,
        ]);

        expect($recurring->isDue())->toBeTrue();
    });

    it('returns true when next_date is in the past', function () {
        $recurring = RecurringTransaction::factory()->make([
            'next_date' => now()->subDays(3),
            'is_active' => true,
        ]);

        expect($recurring->isDue())->toBeTrue();
    });

    it('returns false when next_date is in the future', function () {
        $recurring = RecurringTransaction::factory()->make([
            'next_date' => now()->addDays(3),
            'is_active' => true,
        ]);

        expect($recurring->isDue())->toBeFalse();
    });

    it('returns false when not active', function () {
        $recurring = RecurringTransaction::factory()->make([
            'next_date' => now()->subDay(),
            'is_active' => false,
        ]);

        expect($recurring->isDue())->toBeFalse();
    });

    it('returns false when end_date has passed', function () {
        $recurring = RecurringTransaction::factory()->make([
            'next_date' => now()->subDay(),
            'end_date' => now()->subWeek(),
            'is_active' => true,
        ]);

        expect($recurring->isDue())->toBeFalse();
    });

    it('returns true when end_date is in the future', function () {
        $recurring = RecurringTransaction::factory()->make([
            'next_date' => now()->startOfDay(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
        ]);

        expect($recurring->isDue())->toBeTrue();
    });
});

describe('RecurringTransaction Transaction Generation', function () {
    it('generates transaction when due', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 150.00,
            'description' => 'Test Expense',
            'next_date' => now()->subDay(),
            'is_active' => true,
        ]);

        $transaction = $recurring->generateTransaction();

        expect($transaction)->toBeInstanceOf(Transaction::class);
        expect($transaction->amount)->toBe(150.00);
        expect($transaction->description)->toBe('Test Expense');
        expect($transaction->recurring_transaction_id)->toBe($recurring->id);
        expect($transaction->type)->toBe('expense');
    });

    it('does not generate transaction when not due', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->addWeek(),
            'is_active' => true,
        ]);

        $transaction = $recurring->generateTransaction();

        expect($transaction)->toBeNull();
    });

    it('updates next_date after generating transaction', function () {
        $recurring = RecurringTransaction::factory()->daily()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'next_date' => now()->subDay(),
            'interval' => 1,
            'is_active' => true,
        ]);

        $originalNextDate = $recurring->next_date->copy();

        $recurring->generateTransaction();
        $recurring->refresh();

        expect($recurring->next_date)->toBeGreaterThan($originalNextDate);
        expect($recurring->last_processed)->not->toBeNull();
    });

    it('generates transfer transaction with both accounts', function () {
        $toAccount = Account::factory()->create(['user_id' => $this->user->id]);

        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'transfer',
            'account_id' => $this->account->id,
            'to_account_id' => $toAccount->id,
            'amount' => 500.00,
            'next_date' => now()->subDay(),
            'is_active' => true,
        ]);

        $transaction = $recurring->generateTransaction();

        expect($transaction->type)->toBe('transfer');
        expect($transaction->from_account_id)->toBe($this->account->id);
        expect($transaction->to_account_id)->toBe($toAccount->id);
        expect($transaction->account_id)->toBe($this->account->id);
    });
});

describe('RecurringTransaction Helper Methods', function () {
    it('can skip once and update next_date', function () {
        $recurring = RecurringTransaction::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'next_date' => now(),
            'interval' => 1,
        ]);

        $originalDate = $recurring->next_date->copy();

        $recurring->skipOnce();

        expect($recurring->next_date)->toBeGreaterThan($originalDate);
    });

    it('can pause by setting inactive', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $recurring->pause();

        expect($recurring->is_active)->toBeFalse();
    });

    it('can resume by setting active', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        $recurring->resume();

        expect($recurring->is_active)->toBeTrue();
    });
});

describe('RecurringTransaction Frequency Labels', function () {
    it('generates correct label for daily frequency', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'daily',
            'interval' => 1,
        ]);

        expect($recurring->frequency_label)->toBe('Daily');
    });

    it('generates correct label for every 3 days', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'daily',
            'interval' => 3,
        ]);

        expect($recurring->frequency_label)->toBe('Every 3 days');
    });

    it('generates correct label for weekly with day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'weekly',
            'interval' => 1,
            'day_of_week' => Carbon::MONDAY,
        ]);

        expect($recurring->frequency_label)->toBe('Weekly on Monday');
    });

    it('generates correct label for bi-weekly with day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'weekly',
            'interval' => 2,
            'day_of_week' => Carbon::FRIDAY,
        ]);

        expect($recurring->frequency_label)->toBe('Every 2 weeks on Friday');
    });

    it('generates correct label for monthly with day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 15,
        ]);

        expect($recurring->frequency_label)->toBe('Monthly on day 15');
    });

    it('generates correct label for annual with month and day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'annual',
            'interval' => 1,
            'month_of_year' => 12,
            'day_of_month' => 25,
        ]);

        expect($recurring->frequency_label)->toBe('Annually in December on day 25');
    });
});

describe('RecurringTransaction Scopes', function () {
    it('filters active recurring transactions', function () {
        RecurringTransaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        RecurringTransaction::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        $active = RecurringTransaction::active()->get();

        expect($active)->toHaveCount(3);
        expect($active->every(fn ($r) => $r->is_active === true))->toBeTrue();
    });

    it('filters due recurring transactions', function () {
        RecurringTransaction::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'next_date' => now()->subDay(),
            'is_active' => true,
        ]);
        RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->addWeek(),
            'is_active' => true,
        ]);

        $due = RecurringTransaction::due()->get();

        expect($due)->toHaveCount(2);
    });

    it('excludes due transactions past end_date', function () {
        RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->subDay(),
            'end_date' => now()->subWeek(),
            'is_active' => true,
        ]);

        $due = RecurringTransaction::due()->get();

        expect($due)->toHaveCount(0);
    });

    it('filters upcoming recurring transactions', function () {
        RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->addDays(3),
            'is_active' => true,
        ]);
        RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->addDays(10),
            'is_active' => true,
        ]);

        $upcoming = RecurringTransaction::upcoming(7)->get();

        expect($upcoming)->toHaveCount(1);
    });
});

describe('RecurringTransaction Attributes', function () {
    it('returns correct type color', function () {
        $income = RecurringTransaction::factory()->make(['type' => 'income']);
        $expense = RecurringTransaction::factory()->make(['type' => 'expense']);
        $transfer = RecurringTransaction::factory()->make(['type' => 'transfer']);

        expect($income->type_color)->toBe('success');
        expect($expense->type_color)->toBe('danger');
        expect($transfer->type_color)->toBe('info');
    });

    it('returns correct type icon', function () {
        $income = RecurringTransaction::factory()->make(['type' => 'income']);
        $expense = RecurringTransaction::factory()->make(['type' => 'expense']);
        $transfer = RecurringTransaction::factory()->make(['type' => 'transfer']);

        expect($income->type_icon)->toBe('heroicon-o-arrow-down-circle');
        expect($expense->type_icon)->toBe('heroicon-o-arrow-up-circle');
        expect($transfer->type_icon)->toBe('heroicon-o-arrow-right-circle');
    });

    it('limits occurrences by end_date', function () {
        $recurring = RecurringTransaction::factory()->daily()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->startOfDay(),
            'end_date' => now()->addDays(2)->endOfDay(),
            'interval' => 1,
        ]);

        $occurrences = $recurring->next_occurrences;

        // Should include today, tomorrow, and day after (3 occurrences)
        expect($occurrences)->toHaveCount(3);
    });
});

describe('RecurringTransaction Edge Cases', function () {
    it('handles monthly recurrence across year boundary', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 15,
            'next_date' => Carbon::parse('2024-12-15'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2025-01-15');
    });

    it('handles weekly recurrence with interval across month', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'weekly',
            'interval' => 3,
            'next_date' => Carbon::parse('2024-01-15'),
            'day_of_week' => null,  // No specific day of week
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe('2024-02-05');
    });

    it('handles monthly on 31st for months with 30 days', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'monthly',
            'interval' => 1,
            'day_of_month' => 31,
            'next_date' => Carbon::parse('2024-03-31'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        // April has 30 days, so should be April 30
        expect($nextDate->format('Y-m-d'))->toBe('2024-04-30');
    });

    it('preserves time of day as start of day', function () {
        $recurring = RecurringTransaction::factory()->make([
            'frequency' => 'daily',
            'interval' => 1,
            'next_date' => Carbon::parse('2024-01-15 14:30:00'),
        ]);

        $nextDate = $recurring->calculateNextDate();

        // For monthly with day_of_month, time should be start of day
        expect($nextDate->format('H:i:s'))->toBe('00:00:00');
    });
});
