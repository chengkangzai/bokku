<?php

use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create test data
    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'balance' => 100000, // RM 1000
    ]);

    $this->coffeeCategory = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Coffee & Tea',
    ]);

    $this->foodCategory = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Food & Dining',
    ]);

    $this->transportCategory = Category::factory()->expense()->create([
        'user_id' => $this->user->id,
        'name' => 'Transportation',
    ]);
});

describe('Automatic Rule Application on Transaction Creation', function () {
    it('automatically applies matching rule when creating transaction', function () {
        // Create a rule for Starbucks
        $rule = TransactionRule::factory()->withCategoryAction($this->coffeeCategory->id)->create([
            'user_id' => $this->user->id,
            'name' => 'Categorize Starbucks',
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Starbucks',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_category',
                    'category_id' => $this->coffeeCategory->id,
                ],
                [
                    'type' => 'add_tag',
                    'tag' => 'coffee',
                ],
            ],
        ]);

        // Create transaction through form
        livewire(CreateTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 5.50,
                'date' => now()->format('Y-m-d'),
                'description' => 'Starbucks Coffee Purchase',
                'account_id' => $this->account->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Check that rule was applied
        $transaction = Transaction::where('description', 'Starbucks Coffee Purchase')->first();
        expect($transaction)->not->toBeNull();
        expect($transaction->category_id)->toBe($this->coffeeCategory->id);
        expect($transaction->tags)->toContain('coffee');
        expect($transaction->applied_rule_id)->toBe($rule->id);

        // Check that rule statistics were updated
        $rule->refresh();
        expect($rule->times_applied)->toBe(1);
        expect($rule->last_applied_at)->not->toBeNull();
    });

    it('applies multiple rules in priority order', function () {
        // Create high priority rule
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Tag Large Purchases',
            'priority' => 100,
            'stop_processing' => false,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 100,
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => 'large-purchase',
                ],
            ],
        ]);

        // Create medium priority rule
        $categoryRule = TransactionRule::factory()->withCategoryAction($this->transportCategory->id)->create([
            'user_id' => $this->user->id,
            'name' => 'Categorize Uber',
            'priority' => 50,
            'stop_processing' => false,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Uber',
                ],
            ],
        ]);

        // Create low priority rule
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Add Transport Tag',
            'priority' => 10,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Uber',
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => 'transport',
                ],
            ],
        ]);

        // Create transaction
        livewire(CreateTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 150,
                'date' => now()->format('Y-m-d'),
                'description' => 'Uber Ride to Airport',
                'account_id' => $this->account->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Check all rules were applied in order
        $transaction = Transaction::where('description', 'Uber Ride to Airport')->first();
        expect($transaction->category_id)->toBe($this->transportCategory->id);
        expect($transaction->tags)->toContain('large-purchase');
        expect($transaction->tags)->toContain('transport');
        // The first (highest priority) rule is now tracked, not the last one
        $highPriorityRule = TransactionRule::where('name', 'Tag Large Purchases')->first();
        expect($transaction->applied_rule_id)->toBe($highPriorityRule->id);
    });

    it('respects stop_processing flag', function () {
        // Create rule with stop_processing = true
        $firstRule = TransactionRule::factory()->withCategoryAction($this->foodCategory->id)->create([
            'user_id' => $this->user->id,
            'name' => 'First Rule',
            'priority' => 100,
            'stop_processing' => true,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Restaurant',
                ],
            ],
        ]);

        // Create second rule that would also match
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Second Rule',
            'priority' => 50,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Restaurant',
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => 'should-not-be-applied',
                ],
            ],
        ]);

        // Create transaction
        livewire(CreateTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 75,
                'date' => now()->format('Y-m-d'),
                'description' => 'Restaurant Bill',
                'account_id' => $this->account->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Check only first rule was applied
        $transaction = Transaction::where('description', 'Restaurant Bill')->first();
        expect($transaction->category_id)->toBe($this->foodCategory->id);
        expect($transaction->tags)->not->toContain('should-not-be-applied');
        expect($transaction->applied_rule_id)->toBe($firstRule->id);
    });

    it('does not apply rules to transactions from recurring transactions', function () {
        // Create a rule that would match
        TransactionRule::factory()->withCategoryAction($this->foodCategory->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Subscription',
                ],
            ],
        ]);

        // Create a recurring transaction
        $recurringTransaction = \App\Models\RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->transportCategory->id, // Different category
        ]);

        // Create transaction manually with recurring_transaction_id (bypassing form since it doesn't support this field)
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 10,
            'date' => now(),
            'description' => 'Subscription Payment',
            'account_id' => $this->account->id,
            'recurring_transaction_id' => $recurringTransaction->id,
            'category_id' => $this->transportCategory->id,
        ]);

        // Check rule was NOT applied
        expect($transaction->category_id)->toBe($this->transportCategory->id); // Kept original category
        expect($transaction->applied_rule_id)->toBeNull();
    });
});

describe('Manual Rule Application on Existing Transactions', function () {
    it('can apply rules to single existing transaction', function () {
        // Create transaction without category
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Starbucks Purchase',
            'category_id' => null,
            'applied_rule_id' => null,
        ]);

        // Create matching rule
        $rule = TransactionRule::factory()->withCategoryAction($this->coffeeCategory->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Starbucks',
                ],
            ],
        ]);

        // Apply rules through table action
        livewire(ListTransactions::class)
            ->callTableAction('apply_rules', $transaction)
            ->assertNotified();

        // Check rule was applied
        $transaction->refresh();
        expect($transaction->category_id)->toBe($this->coffeeCategory->id);
        expect($transaction->applied_rule_id)->toBe($rule->id);
    });

    it('can apply rules to multiple transactions in bulk', function () {
        // Create transactions without categories
        $transactions = Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coffee Shop Purchase',
            'category_id' => null,
        ]);

        // Create matching rule
        $rule = TransactionRule::factory()->withCategoryAction($this->coffeeCategory->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Coffee',
                ],
            ],
        ]);

        // Apply rules through bulk action
        livewire(ListTransactions::class)
            ->callTableBulkAction('apply_rules_bulk', $transactions)
            ->assertNotified();

        // Check rules were applied to all transactions
        foreach ($transactions as $transaction) {
            $transaction->refresh();
            expect($transaction->category_id)->toBe($this->coffeeCategory->id);
            expect($transaction->applied_rule_id)->toBe($rule->id);
        }

        $rule->refresh();
        expect($rule->times_applied)->toBe(3);
    });

    it('skips transactions that already have rules applied', function () {
        // Create transaction with existing rule applied
        $existingRule = TransactionRule::factory()->create(['user_id' => $this->user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coffee Purchase',
            'category_id' => $this->foodCategory->id,
            'applied_rule_id' => $existingRule->id,
        ]);

        // Create new matching rule
        $newRule = TransactionRule::factory()->withCategoryAction($this->coffeeCategory->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Coffee',
                ],
            ],
        ]);

        // Try to apply rules
        livewire(ListTransactions::class)
            ->callTableBulkAction('apply_rules_bulk', [$transaction])
            ->assertNotified();

        // Check original rule is still applied
        $transaction->refresh();
        expect($transaction->category_id)->toBe($this->foodCategory->id); // Original category
        expect($transaction->applied_rule_id)->toBe($existingRule->id); // Original rule

        // New rule should not have been applied
        $newRule->refresh();
        expect($newRule->times_applied)->toBe(0);
    });

    it('only shows apply rules action for transactions without applied rules', function () {
        // Create transaction without rule
        $transactionWithoutRule = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'applied_rule_id' => null,
        ]);

        // Create transaction with rule
        $rule = TransactionRule::factory()->create(['user_id' => $this->user->id]);
        $transactionWithRule = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'applied_rule_id' => $rule->id,
        ]);

        // Check action visibility
        livewire(ListTransactions::class)
            ->assertTableActionVisible('apply_rules', $transactionWithoutRule)
            ->assertTableActionHidden('apply_rules', $transactionWithRule);
    });
});

describe('Rule Matching Preview in Transaction Form', function () {
    it('shows matching rules preview when entering description', function () {
        // Create rules with higher priority to ensure order
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Starbucks Rule',
            'is_active' => true,
            'apply_to' => 'all',
            'priority' => 100,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Starbucks',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_category',
                    'category_id' => $this->coffeeCategory->id,
                ],
            ],
        ]);

        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Coffee Rule',
            'is_active' => true,
            'apply_to' => 'expense',
            'priority' => 50,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Coffee',
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => 'coffee',
                ],
            ],
        ]);

        // Fill form with amount to ensure matching works
        livewire(CreateTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 10,
                'description' => 'Starbucks Coffee',
            ])
            ->assertSee('✓ Will apply:')
            ->assertSee('Starbucks Rule')
            ->assertSee('Coffee Rule');
    });

    it('shows matching rules preview when entering amount', function () {
        // Create rule based on amount
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Large Purchase Rule',
            'is_active' => true,
            'apply_to' => 'expense',
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 100,
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => 'large-purchase',
                ],
            ],
        ]);

        // Fill form and check preview
        livewire(CreateTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'amount' => 150,
            ])
            ->assertSee('✓ Will apply: Large Purchase Rule');
    });

    it('shows no matching rules when criteria do not match', function () {
        // Create rule that won't match
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Specific Rule',
            'is_active' => true,
            'apply_to' => 'expense',
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'equals',
                    'value' => 'Exact Match Required',
                ],
            ],
            'actions' => [
                [
                    'type' => 'add_tag',
                    'tag' => 'specific',
                ],
            ],
        ]);

        // Fill form and check preview
        livewire(CreateTransaction::class)
            ->fillForm([
                'type' => 'expense',
                'description' => 'Random Purchase',
            ])
            ->assertSee('No matching rules found');
    });

    it('updates preview when transaction type changes', function () {
        // Create rules for different transaction types
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Income Rule',
            'apply_to' => 'income',
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 100,
                ],
            ],
        ]);

        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Expense Rule',
            'apply_to' => 'expense',
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 100,
                ],
            ],
        ]);

        $component = livewire(CreateTransaction::class);

        // First set as expense with amount > 100
        $component->fillForm([
            'type' => 'expense',
            'amount' => 150,
        ]);

        // Should see expense rule in preview
        $component->assertSee('✓ Will apply: Expense Rule');
        $component->assertDontSee('Income Rule');

        // Change to income type
        $component->set('data.type', 'income');

        // Should now see income rule instead
        $component->assertSee('✓ Will apply: Income Rule');
        $component->assertDontSee('Expense Rule');

        // Change amount to be below threshold
        $component->set('data.amount', 50);

        // Should see no matching rules
        $component->assertSee('No matching rules found');
    });

    it('only shows preview on create page not edit', function () {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        // Create page should show automation section
        $this->get(TransactionResource::getUrl('create'))
            ->assertSee('Automation');

        // Edit page should not show automation section
        $this->get(TransactionResource::getUrl('edit', ['record' => $transaction]))
            ->assertDontSee('Matching Rules'); // More specific text from the automation section
    });
});
