<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\RecurringTransactions\CreateRecurringTransactionTool;
use App\Mcp\Tools\RecurringTransactions\DeleteRecurringTransactionTool;
use App\Mcp\Tools\RecurringTransactions\GetRecurringTransactionTool;
use App\Mcp\Tools\RecurringTransactions\ListRecurringTransactionsTool;
use App\Mcp\Tools\RecurringTransactions\PauseRecurringTransactionTool;
use App\Mcp\Tools\RecurringTransactions\ProcessDueRecurringTransactionsTool;
use App\Mcp\Tools\RecurringTransactions\ResumeRecurringTransactionTool;
use App\Mcp\Tools\RecurringTransactions\SkipRecurringTransactionTool;
use App\Mcp\Tools\RecurringTransactions\UpdateRecurringTransactionTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
});

describe('ListRecurringTransactionsTool', function () {
    it('returns only the users recurring transactions', function () {
        RecurringTransaction::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        RecurringTransaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => null,
            'description' => 'Someone Elses Rent',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListRecurringTransactionsTool::class);

        $response->assertOk()
            ->assertSee('"count":2')
            ->assertDontSee('Someone Elses Rent');
    });

    it('filters by frequency', function () {
        RecurringTransaction::factory()->monthly()->withDescription('Monthly Rent')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);
        RecurringTransaction::factory()->weekly()->withDescription('Weekly Groceries')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListRecurringTransactionsTool::class, [
            'frequency' => 'monthly',
        ]);

        $response->assertOk()
            ->assertSee('Monthly Rent')
            ->assertDontSee('Weekly Groceries');
    });

    it('filters to only due schedules', function () {
        RecurringTransaction::factory()->due()->withDescription('Overdue Bill')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);
        RecurringTransaction::factory()->upcoming()->withDescription('Future Bill')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListRecurringTransactionsTool::class, [
            'due_only' => true,
        ]);

        $response->assertOk()
            ->assertSee('Overdue Bill')
            ->assertDontSee('Future Bill');
    });
});

describe('GetRecurringTransactionTool', function () {
    it('returns the schedule with upcoming occurrences', function () {
        $recurring = RecurringTransaction::factory()->monthly()->withDescription('Netflix')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ]);

        $response->assertOk()
            ->assertSee('Netflix')
            ->assertSee('next_occurrences');
    });

    it('does not expose another users schedule', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => null,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(GetRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ]);

        $response->assertHasErrors();
    });
});

describe('CreateRecurringTransactionTool', function () {
    it('creates a monthly expense schedule', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateRecurringTransactionTool::class, [
            'type' => 'expense',
            'amount' => 49.90,
            'description' => 'Gym Membership',
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        $response->assertOk()->assertSee('Gym Membership');

        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $this->user->id,
            'description' => 'Gym Membership',
            'amount' => 4990,
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'is_active' => true,
        ]);
    });

    it('defaults next_date to the start date', function () {
        BokkuServer::actingAs($this->user)->tool(CreateRecurringTransactionTool::class, [
            'type' => 'expense',
            'amount' => 10,
            'description' => 'Spotify',
            'frequency' => 'monthly',
            'account_id' => $this->account->id,
            'start_date' => '2026-10-01',
        ])->assertOk();

        $recurring = RecurringTransaction::where('description', 'Spotify')->first();

        expect($recurring->next_date->toDateString())->toBe('2026-10-01');
    });

    it('creates a transfer schedule from two accounts', function () {
        $destination = Account::factory()->create(['user_id' => $this->user->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateRecurringTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 500,
            'description' => 'Monthly Savings',
            'frequency' => 'monthly',
            'from_account_id' => $this->account->id,
            'to_account_id' => $destination->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('recurring_transactions', [
            'description' => 'Monthly Savings',
            'account_id' => $this->account->id,
            'to_account_id' => $destination->id,
        ]);
    });

    it('rejects a transfer without a destination account', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateRecurringTransactionTool::class, [
            'type' => 'transfer',
            'amount' => 500,
            'description' => 'Broken Transfer',
            'frequency' => 'monthly',
            'account_id' => $this->account->id,
        ]);

        $response->assertHasErrors();
        $this->assertDatabaseMissing('recurring_transactions', ['description' => 'Broken Transfer']);
    });

    it('rejects an account belonging to another user', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);

        $response = BokkuServer::actingAs($this->user)->tool(CreateRecurringTransactionTool::class, [
            'type' => 'expense',
            'amount' => 20,
            'description' => 'Sneaky',
            'frequency' => 'daily',
            'account_id' => $otherAccount->id,
        ]);

        $response->assertHasErrors();
        $this->assertDatabaseMissing('recurring_transactions', ['description' => 'Sneaky']);
    });

    it('requires a frequency', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateRecurringTransactionTool::class, [
            'type' => 'expense',
            'amount' => 20,
            'description' => 'No Frequency',
            'account_id' => $this->account->id,
        ]);

        $response->assertHasErrors();
    });
});

describe('UpdateRecurringTransactionTool', function () {
    it('updates only the provided fields', function () {
        $recurring = RecurringTransaction::factory()->monthly()->withDescription('Old Name')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateRecurringTransactionTool::class, [
            'id' => $recurring->id,
            'description' => 'New Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $recurring->id,
            'description' => 'New Name',
            'amount' => 10000,
            'frequency' => 'monthly',
        ]);
    });

    it('rejects an end date before the start date', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'start_date' => '2026-01-01',
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateRecurringTransactionTool::class, [
            'id' => $recurring->id,
            'end_date' => '2025-01-01',
        ]);

        $response->assertHasErrors();
    });

    it('cannot update another users schedule', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => null,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(UpdateRecurringTransactionTool::class, [
            'id' => $recurring->id,
            'description' => 'Hijacked',
        ]);

        $response->assertHasErrors();
        $this->assertDatabaseMissing('recurring_transactions', ['description' => 'Hijacked']);
    });
});

describe('DeleteRecurringTransactionTool', function () {
    it('deletes the schedule', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        BokkuServer::actingAs($this->user)->tool(DeleteRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ])->assertOk();

        $this->assertDatabaseMissing('recurring_transactions', ['id' => $recurring->id]);
    });

    it('cannot delete another users schedule', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => null,
        ]);

        BokkuServer::actingAs($this->user)->tool(DeleteRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ])->assertHasErrors();

        $this->assertDatabaseHas('recurring_transactions', ['id' => $recurring->id]);
    });
});

describe('pause, resume and skip tools', function () {
    it('pauses an active schedule', function () {
        $recurring = RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        BokkuServer::actingAs($this->user)->tool(PauseRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ])->assertOk();

        expect($recurring->fresh()->is_active)->toBeFalse();
    });

    it('errors when pausing an already paused schedule', function () {
        $recurring = RecurringTransaction::factory()->inactive()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        BokkuServer::actingAs($this->user)->tool(PauseRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ])->assertHasErrors();
    });

    it('resumes a paused schedule', function () {
        $recurring = RecurringTransaction::factory()->inactive()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        BokkuServer::actingAs($this->user)->tool(ResumeRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ])->assertOk();

        expect($recurring->fresh()->is_active)->toBeTrue();
    });

    it('advances the next date without creating a transaction', function () {
        $recurring = RecurringTransaction::factory()->daily()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'next_date' => now()->toDateString(),
            'interval' => 1,
        ]);

        $originalDate = $recurring->next_date->toDateString();

        BokkuServer::actingAs($this->user)->tool(SkipRecurringTransactionTool::class, [
            'id' => $recurring->id,
        ])->assertOk();

        expect($recurring->fresh()->next_date->toDateString())->not->toBe($originalDate);
        expect($recurring->generatedTransactions()->count())->toBe(0);
    });
});

describe('ProcessDueRecurringTransactionsTool', function () {
    it('generates transactions for due schedules', function () {
        $recurring = RecurringTransaction::factory()->due()->expense()->withDescription('Electricity Bill')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'frequency' => 'monthly',
            'interval' => 1,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ProcessDueRecurringTransactionsTool::class);

        $response->assertOk()->assertSee('"processed_count":1');

        $this->assertDatabaseHas('transactions', [
            'recurring_transaction_id' => $recurring->id,
            'description' => 'Electricity Bill',
        ]);
    });

    it('creates nothing on a dry run', function () {
        $recurring = RecurringTransaction::factory()->due()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'frequency' => 'monthly',
            'interval' => 1,
        ]);

        $nextDate = $recurring->next_date->toDateString();

        BokkuServer::actingAs($this->user)->tool(ProcessDueRecurringTransactionsTool::class, [
            'dry_run' => true,
        ])->assertOk()->assertSee('"processed_count":1');

        expect($recurring->generatedTransactions()->count())->toBe(0);
        expect($recurring->fresh()->next_date->toDateString())->toBe($nextDate);
    });

    it('ignores schedules that are not due', function () {
        RecurringTransaction::factory()->upcoming()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
        ]);

        BokkuServer::actingAs($this->user)->tool(ProcessDueRecurringTransactionsTool::class)
            ->assertOk()
            ->assertSee('"processed_count":0');
    });

    it('processes schedules with auto_process disabled by default', function () {
        RecurringTransaction::factory()->due()->expense()->withDescription('Manual Bill')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'frequency' => 'monthly',
            'interval' => 1,
            'auto_process' => false,
        ]);

        BokkuServer::actingAs($this->user)->tool(ProcessDueRecurringTransactionsTool::class)
            ->assertOk()
            ->assertSee('"processed_count":1');

        $this->assertDatabaseHas('transactions', ['description' => 'Manual Bill']);
    });

    it('restricts to auto_process schedules when asked', function () {
        RecurringTransaction::factory()->due()->expense()->withDescription('Manual Bill')->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'auto_process' => false,
        ]);

        BokkuServer::actingAs($this->user)->tool(ProcessDueRecurringTransactionsTool::class, [
            'auto_process_only' => true,
        ])->assertOk()->assertSee('"processed_count":0');

        $this->assertDatabaseMissing('transactions', ['description' => 'Manual Bill']);
    });

    it('does not process another users due schedules', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        RecurringTransaction::factory()->due()->expense()->withDescription('Other Bill')->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => null,
        ]);

        BokkuServer::actingAs($this->user)->tool(ProcessDueRecurringTransactionsTool::class)
            ->assertOk()
            ->assertSee('"processed_count":0');

        $this->assertDatabaseMissing('transactions', ['description' => 'Other Bill']);
    });
});
