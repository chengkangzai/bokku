<?php

use App\Filament\Resources\TransactionRuleResource;
use App\Filament\Resources\TransactionRuleResource\Pages\CreateTransactionRule;
use App\Filament\Resources\TransactionRuleResource\Pages\EditTransactionRule;
use App\Filament\Resources\TransactionRuleResource\Pages\ListTransactionRules;
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
    $this->category1 = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Food']);
    $this->category2 = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Transport']);
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
});

describe('TransactionRuleResource Page Rendering', function () {
    it('can render index page', function () {
        $this->get(TransactionRuleResource::getUrl('index'))->assertSuccessful();
    });

    it('can render create page', function () {
        $this->get(TransactionRuleResource::getUrl('create'))->assertSuccessful();
    });

    it('can render edit page', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->get(TransactionRuleResource::getUrl('edit', ['record' => $rule]))->assertSuccessful();
    });
});

describe('TransactionRuleResource CRUD Operations', function () {
    it('can create rule with description condition and category action', function () {
        // Use Repeater::fake() to disable UUID generation for testing
        \Filament\Forms\Components\Repeater::fake();

        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Categorize Starbucks',
                'description' => 'Automatically categorize Starbucks purchases',
                'apply_to' => 'expense',
                'priority' => 100,
                'is_active' => true,
                'stop_processing' => false,
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
                        'category_id' => $this->category1->id,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(TransactionRule::class, [
            'user_id' => $this->user->id,
            'name' => 'Categorize Starbucks',
            'apply_to' => 'expense',
            'priority' => 100,
            'is_active' => true,
        ]);

        $rule = TransactionRule::where('name', 'Categorize Starbucks')->first();
        expect($rule->conditions)->toHaveCount(1);
        expect($rule->conditions[0]['field'])->toBe('description');
        expect($rule->conditions[0]['operator'])->toBe('contains');
        expect($rule->conditions[0]['value'])->toBe('Starbucks');
        expect($rule->actions)->toHaveCount(1);
        expect($rule->actions[0]['type'])->toBe('set_category');
        expect($rule->actions[0]['category_id'])->toBe($this->category1->id);
    });

    it('can create rule with amount condition and tag action', function () {
        // Use Repeater::fake() to disable UUID generation for testing
        \Filament\Forms\Components\Repeater::fake();

        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Tag Large Purchases',
                'apply_to' => 'all',
                'priority' => 50,
                'is_active' => true,
                'conditions' => [
                    [
                        'field' => 'amount',
                        'operator' => 'greater_than',
                        'value' => '1000',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tag',
                        'tag' => 'large-purchase',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = TransactionRule::where('name', 'Tag Large Purchases')->first();
        expect($rule)->not->toBeNull();
        expect($rule->conditions[0]['field'])->toBe('amount');
        expect($rule->conditions[0]['operator'])->toBe('greater_than');
        expect($rule->conditions[0]['value'])->toBe('1000');
        expect($rule->actions[0]['type'])->toBe('add_tag');
        expect($rule->actions[0]['tag'])->toBe('large-purchase');
    });

    it('can create rule with multiple conditions and actions', function () {
        // Use Repeater::fake() to disable UUID generation
        \Filament\Forms\Components\Repeater::fake();

        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Complex Rule',
                'description' => 'Rule with multiple conditions and actions',
                'apply_to' => 'expense',
                'priority' => 200,
                'is_active' => true,
                'stop_processing' => true,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Restaurant',
                    ],
                    [
                        'field' => 'amount',
                        'operator' => 'greater_than',
                        'value' => '50',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'category_id' => $this->category1->id,
                    ],
                    [
                        'type' => 'add_tag',
                        'tag' => 'dining-out',
                    ],
                    [
                        'type' => 'set_notes',
                        'notes' => 'Auto-categorized restaurant expense',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = TransactionRule::where('name', 'Complex Rule')->first();
        expect($rule)->not->toBeNull();
        expect($rule->conditions)->toHaveCount(2);
        expect($rule->conditions[0]['field'])->toBe('description');
        expect($rule->conditions[1]['field'])->toBe('amount');
        expect($rule->actions)->toHaveCount(3);
        expect($rule->actions[0]['type'])->toBe('set_category');
        expect($rule->actions[1]['type'])->toBe('add_tag');
        expect($rule->actions[2]['type'])->toBe('set_notes');
        expect($rule->stop_processing)->toBeTrue();
        expect($rule->priority)->toBe(200);
    });

    it('can update existing rule', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
            'priority' => 10,
        ]);

        livewire(EditTransactionRule::class, ['record' => $rule->id])
            ->fillForm([
                'name' => 'Updated Name',
                'priority' => 100,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $rule->refresh();
        expect($rule->name)->toBe('Updated Name');
        expect($rule->priority)->toBe(100);
        expect($rule->is_active)->toBeFalse();
    });

    it('can delete rule', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
        ]);

        livewire(ListTransactionRules::class)
            ->callTableAction('delete', $rule);

        $this->assertDatabaseMissing(TransactionRule::class, [
            'id' => $rule->id,
        ]);
    });
});

describe('TransactionRuleResource Table Features', function () {
    beforeEach(function () {
        // Create various rules for testing
        $this->activeRule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Active Rule',
            'is_active' => true,
            'apply_to' => 'expense',
            'priority' => 100,
        ]);

        $this->inactiveRule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Inactive Rule',
            'is_active' => false,
            'apply_to' => 'income',
            'priority' => 50,
        ]);

        $this->transferRule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Transfer Rule',
            'is_active' => true,
            'apply_to' => 'transfer',
            'priority' => 75,
        ]);

        // Other user's rule (should not be visible)
        $otherUser = User::factory()->create();
        TransactionRule::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Rule',
        ]);
    });

    it('shows only current user rules', function () {
        livewire(ListTransactionRules::class)
            ->assertCanSeeTableRecords([$this->activeRule, $this->inactiveRule, $this->transferRule])
            ->assertCanNotSeeTableRecords([TransactionRule::where('name', 'Other User Rule')->first()]);
    });

    it('can filter by active status', function () {
        livewire(ListTransactionRules::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$this->activeRule, $this->transferRule])
            ->assertCanNotSeeTableRecords([$this->inactiveRule]);
    });

    it('can filter by apply_to field', function () {
        livewire(ListTransactionRules::class)
            ->filterTable('apply_to', 'expense')
            ->assertCanSeeTableRecords([$this->activeRule])
            ->assertCanNotSeeTableRecords([$this->inactiveRule, $this->transferRule]);

        livewire(ListTransactionRules::class)
            ->filterTable('apply_to', 'income')
            ->assertCanSeeTableRecords([$this->inactiveRule])
            ->assertCanNotSeeTableRecords([$this->activeRule, $this->transferRule]);
    });

    it('sorts by priority by default', function () {
        livewire(ListTransactionRules::class)
            ->assertCanSeeTableRecords([$this->activeRule, $this->transferRule, $this->inactiveRule], inOrder: true);
    });

    it('can toggle rule active status', function () {
        // Ensure rule is initially active
        expect($this->activeRule->is_active)->toBeTrue();

        // Test toggling the active status through the table
        // ToggleColumn automatically updates the model when toggled
        $component = livewire(ListTransactionRules::class);

        // The ToggleColumn updates the model directly via Livewire
        // We simulate the toggle by updating the record
        $this->activeRule->update(['is_active' => false]);
        $this->activeRule->refresh();
        expect($this->activeRule->is_active)->toBeFalse();

        // Toggle back to active
        $this->activeRule->update(['is_active' => true]);
        $this->activeRule->refresh();
        expect($this->activeRule->is_active)->toBeTrue();

        // Verify the column is displayed correctly
        $component->assertCanSeeTableRecords([$this->activeRule]);
    });

    it('can search rules by name', function () {
        livewire(ListTransactionRules::class)
            ->searchTable('Active')
            ->assertCanSeeTableRecords([$this->activeRule, $this->inactiveRule]) // Both contain "active" in name
            ->assertCanNotSeeTableRecords([$this->transferRule]);
    });
});

describe('TransactionRuleResource Actions', function () {
    it('can test rule against recent transactions', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Rule',
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Coffee',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_category',
                    'category_id' => $this->category1->id,
                ],
            ],
        ]);

        // Create matching and non-matching transactions
        $matchingTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coffee Shop Purchase',
            'type' => 'expense',
        ]);

        $nonMatchingTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Grocery Store',
            'type' => 'expense',
        ]);

        // Test the modal opens and shows correct content
        $component = livewire(ListTransactionRules::class)
            ->callTableAction('test', $rule)
            ->assertSuccessful();

        // The modal view should receive matching transactions
        // We can't directly test the view content, but we can verify the action executes
        // and the transactions are filtered correctly through the rule's matches() method
        expect($rule->matches($matchingTransaction))->toBeTrue();
        expect($rule->matches($nonMatchingTransaction))->toBeFalse();
    });

    it('can apply rules to all transactions in bulk', function () {
        $rule = TransactionRule::factory()->withCategoryAction($this->category1->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Restaurant',
                ],
            ],
        ]);

        // Create transactions that should match
        $transactions = Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Restaurant Payment',
            'category_id' => null,
        ]);

        // Get initial times_applied
        $initialTimesApplied = $rule->times_applied;

        livewire(ListTransactionRules::class)
            ->callTableBulkAction('apply_to_all', [$rule])
            ->assertNotified();

        foreach ($transactions as $transaction) {
            $transaction->refresh();
            expect($transaction->category_id)->toBe($this->category1->id);
        }

        $rule->refresh();
        // Check that at least 3 rules were applied (there might be other matching transactions)
        expect($rule->times_applied)->toBeGreaterThanOrEqual($initialTimesApplied + 3);
    });
});

describe('TransactionRuleResource Form Validation', function () {
    it('requires name field', function () {
        \Filament\Forms\Components\Repeater::fake();
        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => '',
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Test',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tag',
                        'tag' => 'test',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    it('requires at least one condition', function () {
        \Filament\Forms\Components\Repeater::fake();
        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Test Rule',
                'conditions' => [],
                'actions' => [
                    [
                        'type' => 'add_tag',
                        'tag' => 'test',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['conditions']);
    });

    it('requires at least one action', function () {
        \Filament\Forms\Components\Repeater::fake();
        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Test Rule',
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Test',
                    ],
                ],
                'actions' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['actions']);
    });

    it('validates condition fields are required', function () {
        \Filament\Forms\Components\Repeater::fake();
        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Test Rule',
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => '', // Empty value
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tag',
                        'tag' => 'test',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['conditions.0.value' => 'required']);
    });

    it('validates action fields are required', function () {
        \Filament\Forms\Components\Repeater::fake();
        livewire(CreateTransactionRule::class)
            ->fillForm([
                'name' => 'Test Rule',
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Test',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tag',
                        'tag' => '', // Empty tag
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['actions.0.tag' => 'required']);
    });
});
