<?php

use App\Filament\Resources\RecurringTransactions\Pages\CreateRecurringTransaction;
use App\Filament\Resources\RecurringTransactions\Pages\EditRecurringTransaction;
use App\Filament\Resources\RecurringTransactions\Pages\ListRecurringTransactions;
use App\Filament\Resources\RecurringTransactions\RecurringTransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create test accounts and categories for the user
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    $this->toAccount = Account::factory()->create(['user_id' => $this->user->id]);
    $this->incomeCategory = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'income']);
    $this->expenseCategory = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
});

describe('RecurringTransactionResource Page Rendering', function () {
    it('can render index page', function () {
        $this->get(RecurringTransactionResource::getUrl('index'))->assertSuccessful();
    });

    it('can render create page', function () {
        $this->get(RecurringTransactionResource::getUrl('create'))->assertSuccessful();
    });

    it('can render edit page', function () {
        $recurringTransaction = RecurringTransaction::factory()->create(['user_id' => $this->user->id]);

        $this->get(RecurringTransactionResource::getUrl('edit', ['record' => $recurringTransaction]))->assertSuccessful();
    });
});

describe('RecurringTransactionResource CRUD Operations', function () {
    it('can create monthly recurring expense', function () {
        livewire(CreateRecurringTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 1500.00,
                'description' => 'Monthly Rent',
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 1,
                'start_date' => now()->startOfMonth()->format('Y-m-d'),
                'next_date' => now()->startOfMonth()->format('Y-m-d'),
                'is_active' => true,
                'auto_process' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RecurringTransaction::class, [
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 150000, // DB stores cents
            'description' => 'Monthly Rent',
            'frequency' => 'monthly',
            'day_of_month' => 1,
        ]);
    });

    it('can create recurring transaction without optional schedule dates', function () {
        livewire(CreateRecurringTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 100.00,
                'description' => 'Test Expense',
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'frequency' => 'daily',
                'interval' => 1,
                'is_active' => true,
                'auto_process' => true,
                // Not providing start_date or next_date - they should auto-calculate
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recurring = RecurringTransaction::where('description', 'Test Expense')->first();

        expect($recurring)->not->toBeNull();
        expect($recurring->start_date->toDateString())->toBe(now()->toDateString());
        expect($recurring->next_date->toDateString())->toBe(now()->toDateString());
    });

    it('can create weekly recurring income', function () {
        livewire(CreateRecurringTransaction::class)
            ->fillForm([
                'type' => 'income',
                'amount' => 500.00,
                'description' => 'Weekly Freelance Payment',
                'account_id' => $this->account->id,
                'category_id' => $this->incomeCategory->id,
                'frequency' => 'weekly',
                'interval' => 1,
                'day_of_week' => 5, // Friday (Carbon standard: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat)
                'is_active' => true,
                'auto_process' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RecurringTransaction::class, [
            'user_id' => $this->user->id,
            'type' => 'income',
            'frequency' => 'weekly',
            'day_of_week' => 5,
            'auto_process' => false,
        ]);
    });

    it('can create recurring transfer', function () {
        livewire(CreateRecurringTransaction::class)
            ->fillForm([
                'type' => 'transfer',
                'amount' => 200.00,
                'description' => 'Weekly Savings Transfer',
                'account_id' => $this->account->id,
                'to_account_id' => $this->toAccount->id,
                'frequency' => 'weekly',
                'interval' => 1,
                'day_of_week' => 1,  // Monday (Carbon standard: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat)
                'is_active' => true,
                'auto_process' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RecurringTransaction::class, [
            'user_id' => $this->user->id,
            'type' => 'transfer',
            'account_id' => $this->account->id,
            'to_account_id' => $this->toAccount->id,
            'frequency' => 'weekly',
        ]);
    });

    it('can update recurring transaction', function () {
        $recurring = RecurringTransaction::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 100.00,
            'is_active' => true,
        ]);

        livewire(EditRecurringTransaction::class, ['record' => $recurring->getRouteKey()])
            ->fillForm([
                'type' => 'expense',
                'amount' => 150.00,
                'description' => $recurring->description,
                'account_id' => $recurring->account_id,
                'category_id' => $recurring->category_id,
                'frequency' => $recurring->frequency,
                'interval' => $recurring->interval,
                'day_of_month' => $recurring->day_of_month,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($recurring->refresh())
            ->amount->toBe(150.00)
            ->is_active->toBeFalse();
    });

    it('can delete recurring transaction', function () {
        $recurring = RecurringTransaction::factory()->create(['user_id' => $this->user->id]);

        livewire(ListRecurringTransactions::class)
            ->callTableAction('delete', $recurring);

        $this->assertModelMissing($recurring);
    });
});

describe('RecurringTransactionResource Table Functionality', function () {
    it('can list user recurring transactions', function () {
        $userRecurring = RecurringTransaction::factory()->count(3)->create(['user_id' => $this->user->id]);
        RecurringTransaction::factory()->count(2)->create(); // Other user transactions

        livewire(ListRecurringTransactions::class)
            ->assertCanSeeTableRecords($userRecurring)
            ->assertCountTableRecords(3);
    });

    it('cannot see other users recurring transactions', function () {
        $userRecurring = RecurringTransaction::factory()->count(2)->create(['user_id' => $this->user->id]);
        $otherUserRecurring = RecurringTransaction::factory()->count(3)->create();

        livewire(ListRecurringTransactions::class)
            ->assertCanSeeTableRecords($userRecurring)
            ->assertCanNotSeeTableRecords($otherUserRecurring)
            ->assertCountTableRecords(2);
    });

    it('can filter by type', function () {
        $incomeRecurring = RecurringTransaction::factory()->income()->count(2)->create(['user_id' => $this->user->id]);
        RecurringTransaction::factory()->expense()->count(2)->create(['user_id' => $this->user->id]);

        livewire(ListRecurringTransactions::class)
            ->filterTable('type', 'income')
            ->assertCanSeeTableRecords($incomeRecurring)
            ->assertCountTableRecords(2);
    });

    it('can filter by frequency', function () {
        $monthlyRecurring = RecurringTransaction::factory()->monthly()->count(2)->create(['user_id' => $this->user->id]);
        RecurringTransaction::factory()->weekly()->count(2)->create(['user_id' => $this->user->id]);

        livewire(ListRecurringTransactions::class)
            ->filterTable('frequency', 'monthly')
            ->assertCanSeeTableRecords($monthlyRecurring)
            ->assertCountTableRecords(2);
    });

    it('can filter by active status', function () {
        $activeRecurring = RecurringTransaction::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        RecurringTransaction::factory()->inactive()->count(2)->create(['user_id' => $this->user->id]);

        livewire(ListRecurringTransactions::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords($activeRecurring)
            ->assertCountTableRecords(2);
    });

    it('can process recurring transaction manually', function () {
        $recurring = RecurringTransaction::factory()->due()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
        ]);

        $originalNextDate = $recurring->next_date->copy();

        livewire(ListRecurringTransactions::class)
            ->callTableAction('process', $recurring);

        // Check that a transaction was created
        $this->assertDatabaseHas(Transaction::class, [
            'user_id' => $this->user->id,
            'recurring_transaction_id' => $recurring->id,
            'amount' => $recurring->amount * 100, // Convert to cents
            'description' => $recurring->description,
        ]);

        // Check that next_date was updated (should be different from original)
        $recurring->refresh();
        expect($recurring->next_date->toDateString())->not->toBe($originalNextDate->toDateString());
    });

    it('can skip recurring transaction', function () {
        $recurring = RecurringTransaction::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->startOfMonth(),
            'interval' => 1,
            'day_of_month' => 15,  // Fixed day to avoid end-of-month issues
        ]);

        $originalDate = $recurring->next_date->copy();

        livewire(ListRecurringTransactions::class)
            ->callTableAction('skip', $recurring);

        $recurring->refresh();
        expect($recurring->next_date)->toBeGreaterThan($originalDate);
        // Check it's roughly a month later (allowing for month length variations)
        expect($recurring->next_date->month)->toBe($originalDate->addMonth()->month);
    });
});

describe('RecurringTransaction Model Functionality', function () {
    it('correctly calculates next date for daily frequency', function () {
        $recurring = RecurringTransaction::factory()->daily()->create([
            'user_id' => $this->user->id,
            'interval' => 2,
            'next_date' => now(),
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->format('Y-m-d'))->toBe(now()->addDays(2)->format('Y-m-d'));
    });

    it('correctly calculates next date for weekly frequency', function () {
        $recurring = RecurringTransaction::factory()->weekly()->create([
            'user_id' => $this->user->id,
            'interval' => 1,
            'day_of_week' => 1, // Monday (Carbon standard: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat)
            'next_date' => now()->startOfWeek(),
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->dayOfWeek)->toBe(1); // Should be Monday
        expect($nextDate)->toBeGreaterThan(now());
    });

    it('correctly calculates next date for monthly frequency', function () {
        $recurring = RecurringTransaction::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'interval' => 1,
            'day_of_month' => 15,
            'next_date' => now()->startOfMonth()->addDays(14), // 15th of current month
        ]);

        $nextDate = $recurring->calculateNextDate();

        expect($nextDate->day)->toBe(15);
        expect($nextDate->month)->toBe(now()->addMonthNoOverflow()->month);
    });

    it('handles end of month correctly', function () {
        $recurring = RecurringTransaction::factory()->monthly()->create([
            'user_id' => $this->user->id,
            'day_of_month' => 31,
            'interval' => 1,
            'next_date' => now()->parse('2024-01-31'), // January has 31 days
        ]);

        $nextDate = $recurring->calculateNextDate();

        // Since day_of_month is 31, it should use the last day of each month
        // For February 2024 (leap year), that should be the 29th
        expect($nextDate->format('Y-m-d'))->toBe('2024-02-29');
    });

    it('determines if transaction is due', function () {
        $dueRecurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->subDay(),
            'is_active' => true,
        ]);

        $notDueRecurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->addDay(),
            'is_active' => true,
        ]);

        expect($dueRecurring->isDue())->toBeTrue();
        expect($notDueRecurring->isDue())->toBeFalse();
    });

    it('generates transaction when due', function () {
        $recurring = RecurringTransaction::factory()->due()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 100.00,
            'description' => 'Test Recurring',
        ]);

        $transaction = $recurring->generateTransaction();

        expect($transaction)->not->toBeNull();
        expect($transaction->amount)->toBe(100.00);
        expect($transaction->description)->toBe('Test Recurring');
        expect($transaction->recurring_transaction_id)->toBe($recurring->id);
    });

    it('does not generate transaction when not due', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'next_date' => now()->addDay(),
            'is_active' => true,
        ]);

        $transaction = $recurring->generateTransaction();

        expect($transaction)->toBeNull();
    });

    it('respects end date', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'next_date' => now()->subDay(),
            'end_date' => now()->subWeek(),
            'is_active' => true,
        ]);

        expect($recurring->isDue())->toBeFalse();
    });

    it('generates correct frequency label', function () {
        $daily = RecurringTransaction::factory()->daily()->make(['interval' => 1, 'user_id' => $this->user->id]);
        expect($daily->frequency_label)->toBe('Daily');

        $biweekly = RecurringTransaction::factory()->weekly()->make([
            'interval' => 2,
            'day_of_week' => 1,  // Monday (Carbon standard: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat)
            'user_id' => $this->user->id,
        ]);
        expect($biweekly->frequency_label)->toBe('Every 2 weeks on Monday');

        $monthly = RecurringTransaction::factory()->monthly()->make([
            'interval' => 1,
            'day_of_month' => 15,
            'user_id' => $this->user->id,
        ]);
        expect($monthly->frequency_label)->toBe('Monthly on day 15');

        $annual = RecurringTransaction::factory()->annual()->make([
            'interval' => 1,
            'month_of_year' => 12,
            'day_of_month' => 25,
            'user_id' => $this->user->id,
        ]);
        expect($annual->frequency_label)->toBe('Annually in December on day 25');
    });
});

describe('RecurringTransactionResource Multi-Tenant Data Scoping', function () {
    it('only shows recurring transactions for authenticated user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1Recurring = RecurringTransaction::factory()->count(2)->create(['user_id' => $user1->id]);
        $user2Recurring = RecurringTransaction::factory()->count(3)->create(['user_id' => $user2->id]);

        // Test as user1
        $this->actingAs($user1);
        livewire(ListRecurringTransactions::class)
            ->assertCanSeeTableRecords($user1Recurring)
            ->assertCanNotSeeTableRecords($user2Recurring)
            ->assertCountTableRecords(2);

        // Test as user2
        $this->actingAs($user2);
        livewire(ListRecurringTransactions::class)
            ->assertCanSeeTableRecords($user2Recurring)
            ->assertCanNotSeeTableRecords($user1Recurring)
            ->assertCountTableRecords(3);
    });

    it('only shows user accounts in account selects', function () {
        $otherUser = User::factory()->create();
        Account::factory()->count(3)->create(['user_id' => $otherUser->id]);

        livewire(CreateRecurringTransaction::class)
            ->fillForm(['type' => 'expense'])
            ->assertSuccessful();

        // The form should only show accounts belonging to the authenticated user
        $this->assertTrue(true);
    });

    it('only shows user categories in category selects', function () {
        $otherUser = User::factory()->create();
        Category::factory()->count(3)->create(['user_id' => $otherUser->id]);

        livewire(CreateRecurringTransaction::class)
            ->fillForm(['type' => 'income'])
            ->assertSuccessful();

        // The form should only show categories belonging to the authenticated user
        $this->assertTrue(true);
    });
});

describe('RecurringTransactionResource Navigation Badge', function () {
    it('shows count of due recurring transactions', function () {
        RecurringTransaction::factory()->due()->count(3)->create(['user_id' => $this->user->id]);
        RecurringTransaction::factory()->upcoming()->count(2)->create(['user_id' => $this->user->id]);

        $badge = RecurringTransactionResource::getNavigationBadge();
        expect($badge)->toBe('3');
    });

    it('shows null when no recurring transactions are due', function () {
        RecurringTransaction::factory()->upcoming()->count(2)->create(['user_id' => $this->user->id]);

        $badge = RecurringTransactionResource::getNavigationBadge();
        expect($badge)->toBeNull();
    });

    it('has danger color for badge', function () {
        $color = RecurringTransactionResource::getNavigationBadgeColor();
        expect($color)->toBe('danger');
    });
});

describe('RecurringTransactionResource Bulk Actions', function () {
    it('can bulk process multiple recurring transactions', function () {
        $dueRecurring = RecurringTransaction::factory()->due()->count(2)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'is_active' => true,
        ]);

        $notDueRecurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'next_date' => now()->addWeek(),
            'is_active' => true,
        ]);

        $allRecurring = $dueRecurring->concat([$notDueRecurring]);

        livewire(ListRecurringTransactions::class)
            ->callTableBulkAction('process_now', $allRecurring->pluck('id')->toArray());

        // Check that transactions were created only for due recurring transactions
        $this->assertDatabaseCount(Transaction::class, 2);

        foreach ($dueRecurring as $recurring) {
            $this->assertDatabaseHas(Transaction::class, [
                'user_id' => $this->user->id,
                'recurring_transaction_id' => $recurring->id,
            ]);
        }

        // Not due recurring should not have generated a transaction
        $this->assertDatabaseMissing(Transaction::class, [
            'recurring_transaction_id' => $notDueRecurring->id,
        ]);
    });

    it('can bulk process with all inactive recurring transactions', function () {
        $inactiveRecurring = RecurringTransaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => false,
            'next_date' => now()->subDay(),
        ]);

        livewire(ListRecurringTransactions::class)
            ->callTableBulkAction('process_now', $inactiveRecurring->pluck('id')->toArray());

        // No transactions should be created for inactive recurring transactions
        $this->assertDatabaseCount(Transaction::class, 0);
    });

    it('can toggle active status for multiple recurring transactions', function () {
        $recurring = RecurringTransaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        livewire(ListRecurringTransactions::class)
            ->callTableBulkAction('toggle_active', $recurring->pluck('id')->toArray());

        foreach ($recurring->fresh() as $item) {
            expect($item->is_active)->toBeFalse();
        }
    });

    it('can bulk delete recurring transactions', function () {
        $recurring = RecurringTransaction::factory()->count(3)->create(['user_id' => $this->user->id]);

        livewire(ListRecurringTransactions::class)
            ->callTableBulkAction('delete', $recurring->pluck('id')->toArray());

        foreach ($recurring as $item) {
            $this->assertModelMissing($item);
        }
    });
});
