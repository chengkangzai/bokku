<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Tags\Tag;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
});

describe('TransactionRule Matching', function () {
    it('matches transactions with description contains condition', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Starbucks',
                ],
            ],
        ]);

        $matchingTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Purchase at Starbucks Coffee',
        ]);

        $nonMatchingTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Grocery Store Purchase',
        ]);

        expect($rule->matches($matchingTransaction))->toBeTrue();
        expect($rule->matches($nonMatchingTransaction))->toBeFalse();
    });

    it('matches transactions with amount greater than condition', function () {
        $rule = TransactionRule::factory()->withAmountCondition('greater_than', 100)->create([
            'user_id' => $this->user->id,
        ]);

        $expensiveTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 150, // RM 150
        ]);

        $cheapTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 50, // RM 50
        ]);

        expect($rule->matches($expensiveTransaction))->toBeTrue();
        expect($rule->matches($cheapTransaction))->toBeFalse();
    });

    it('matches only specified transaction types', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'apply_to' => 'expense',
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Payment',
                ],
            ],
        ]);

        $expense = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'description' => 'Payment for Services',
        ]);

        $income = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'income',
            'description' => 'Payment Received',
        ]);

        expect($rule->matches($expense))->toBeTrue();
        expect($rule->matches($income))->toBeFalse();
    });

    it('requires all conditions to match', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Coffee',
                ],
                [
                    'field' => 'amount',
                    'operator' => 'less_than',
                    'value' => 10, // Less than RM 10
                ],
            ],
        ]);

        $cheapCoffee = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coffee Shop',
            'amount' => 8, // RM 8
        ]);

        $expensiveCoffee = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coffee Shop',
            'amount' => 15, // RM 15
        ]);

        $cheapFood = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Fast Food',
            'amount' => 8, // RM 8
        ]);

        expect($rule->matches($cheapCoffee))->toBeTrue();
        expect($rule->matches($expensiveCoffee))->toBeFalse();
        expect($rule->matches($cheapFood))->toBeFalse();
    });

    it('does not match inactive rules', function () {
        $rule = TransactionRule::factory()->inactive()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Test',
                ],
            ],
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Test Transaction',
        ]);

        expect($rule->matches($transaction))->toBeFalse();
    });
});

describe('TransactionRule Actions', function () {
    it('applies category action to matching transaction', function () {
        $coffeeCategory = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Coffee',
        ]);

        $rule = TransactionRule::factory()->withCategoryAction($coffeeCategory->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Starbucks',
                ],
            ],
        ]);

        // Create transaction without triggering events to test manual application
        $transaction = Transaction::factory()->make([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Starbucks Coffee',
            'category_id' => null,
        ]);
        $transaction->saveQuietly();

        $rule->apply($transaction);
        $transaction->refresh();

        expect($transaction->category_id)->toBe($coffeeCategory->id);
        expect($rule->fresh()->times_applied)->toBe(1);
    });

    it('applies notes action to matching transaction', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 1000, // Greater than RM 1000
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_notes',
                    'notes' => 'Large purchase - review needed',
                ],
            ],
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 1500, // RM 1500
            'notes' => null,
        ]);

        $rule->apply($transaction);
        $transaction->refresh();

        expect($transaction->notes)->toBe('Large purchase - review needed');
    });

    it('applies add_tag action to matching transaction', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
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
                    'tag' => 'coffee-shop',
                ],
            ],
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Coffee at Starbucks',
        ]);

        $rule->apply($transaction);
        $transaction->refresh();

        expect($transaction->tags)->toHaveCount(1);
        expect($transaction->tags->first()->name)->toBe('coffee-shop');
        expect($transaction->tags->first()->type)->toBe('user_'.$this->user->id);
    });

    it('applies multiple actions to matching transaction', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Subscriptions',
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Netflix',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_category',
                    'category_id' => $category->id,
                ],
                [
                    'type' => 'set_notes',
                    'notes' => 'Monthly subscription',
                ],
                [
                    'type' => 'add_tag',
                    'tag' => 'subscription',
                ],
            ],
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Netflix Monthly Subscription',
        ]);

        $rule->apply($transaction);
        $transaction->refresh();

        expect($transaction->category_id)->toBe($category->id);
        expect($transaction->notes)->toBe('Monthly subscription');
        expect($transaction->tags)->toHaveCount(1);
        expect($transaction->tags->first()->name)->toBe('subscription');
    });

    it('records which rule was applied', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Test',
                ],
            ],
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Test Transaction',
        ]);

        $rule->apply($transaction);
        $transaction->refresh();

        expect($transaction->applied_rule_id)->toBe($rule->id);
    });
});

describe('Automatic Rule Application', function () {
    it('automatically applies rules to new transactions', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Transportation',
        ]);

        $rule = TransactionRule::factory()->withCategoryAction($category->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Grab',
                ],
            ],
        ]);

        // Create a new transaction - rules should apply automatically
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => 25,
            'description' => 'Grab Ride to Airport',
            'date' => now(),
        ]);

        expect($transaction->category_id)->toBe($category->id);
        expect($transaction->applied_rule_id)->toBe($rule->id);
    });

    it('applies rules in priority order', function () {
        $category1 = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Transportation',
        ]);

        $category2 = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Business',
        ]);

        // Higher priority rule (should apply first)
        $highPriorityRule = TransactionRule::factory()->withCategoryAction($category2->id)->create([
            'user_id' => $this->user->id,
            'priority' => 100,
            'stop_processing' => true,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Grab',
                ],
            ],
        ]);

        // Lower priority rule (should not apply due to stop_processing)
        $lowPriorityRule = TransactionRule::factory()->withCategoryAction($category1->id)->create([
            'user_id' => $this->user->id,
            'priority' => 10,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Grab',
                ],
            ],
        ]);

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => 25,
            'description' => 'Grab Ride',
            'date' => now(),
        ]);

        expect($transaction->category_id)->toBe($category2->id);
        expect($transaction->applied_rule_id)->toBe($highPriorityRule->id);
    });

    it('does not apply rules to transactions from recurring transactions', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Rent',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_notes',
                    'notes' => 'Monthly recurring payment',
                ],
            ],
        ]);

        // Create a recurring transaction first
        $recurringTransaction = \App\Models\RecurringTransaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        // Create a transaction from a recurring transaction
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => 2000,
            'description' => 'Monthly Rent',
            'date' => now(),
            'recurring_transaction_id' => $recurringTransaction->id, // Use the actual recurring transaction
        ]);

        expect($transaction->notes)->toBeNull();
        expect($transaction->applied_rule_id)->toBeNull();
    });
});

describe('Bulk Rule Application', function () {
    it('can apply rules to existing transactions', function () {
        $category = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Food & Dining',
        ]);

        // Create existing transactions without category
        $transactions = Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'McDonald\'s Restaurant',
            'category_id' => null,
        ]);

        $rule = TransactionRule::factory()->withCategoryAction($category->id)->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'McDonald',
                ],
            ],
        ]);

        // Apply rule to all existing transactions
        foreach ($transactions as $transaction) {
            if ($rule->matches($transaction)) {
                $rule->apply($transaction);
            }
        }

        foreach ($transactions as $transaction) {
            $transaction->refresh();
            expect($transaction->category_id)->toBe($category->id);
        }

        expect($rule->fresh()->times_applied)->toBe(3);
    });
});

describe('Advanced Rule Conditions', function () {
    it('matches transactions with regex pattern', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'regex',
                    'value' => 'UBER.*EATS|DoorDash|GrubHub',
                ],
            ],
        ]);

        $uberEats = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'UBER EATS Purchase',
        ]);

        $doorDash = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'DoorDash Order #12345',
        ]);

        $regularUber = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'UBER Ride',
        ]);

        expect($rule->matches($uberEats))->toBeTrue();
        expect($rule->matches($doorDash))->toBeTrue();
        expect($rule->matches($regularUber))->toBeFalse();
    });

    it('matches transactions with starts_with condition', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'starts_with',
                    'value' => 'Amazon',
                ],
            ],
        ]);

        $amazonPurchase = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Amazon Purchase #123',
        ]);

        $primeVideo = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Prime Video Amazon',
        ]);

        expect($rule->matches($amazonPurchase))->toBeTrue();
        expect($rule->matches($primeVideo))->toBeFalse();
    });

    it('matches transactions with ends_with condition', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'ends_with',
                    'value' => 'Subscription',
                ],
            ],
        ]);

        $netflix = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Netflix Monthly Subscription',
        ]);

        $subscriptionInfo = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Subscription renewal pending',
        ]);

        expect($rule->matches($netflix))->toBeTrue();
        expect($rule->matches($subscriptionInfo))->toBeFalse();
    });

    it('matches transactions with not_contains condition', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'not_contains',
                    'value' => 'Refund',
                ],
            ],
        ]);

        $purchase = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Store Purchase',
        ]);

        $refund = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Store Purchase Refund',
        ]);

        expect($rule->matches($purchase))->toBeTrue();
        expect($rule->matches($refund))->toBeFalse();
    });

    it('matches transactions with amount range conditions', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than_or_equal',
                    'value' => 50,
                ],
                [
                    'field' => 'amount',
                    'operator' => 'less_than_or_equal',
                    'value' => 100,
                ],
            ],
        ]);

        $inRange = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 75,
        ]);

        $tooLow = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 25,
        ]);

        $tooHigh = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 150,
        ]);

        expect($rule->matches($inRange))->toBeTrue();
        expect($rule->matches($tooLow))->toBeFalse();
        expect($rule->matches($tooHigh))->toBeFalse();
    });

    it('matches transactions with category condition', function () {
        $foodCategory = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Food',
        ]);

        $transportCategory = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Transport',
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [
                [
                    'field' => 'category_id',
                    'operator' => 'equals',
                    'value' => $foodCategory->id,
                ],
            ],
        ]);

        $foodTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $foodCategory->id,
        ]);

        $transportTransaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $transportCategory->id,
        ]);

        $uncategorized = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => null,
        ]);

        expect($rule->matches($foodTransaction))->toBeTrue();
        expect($rule->matches($transportTransaction))->toBeFalse();
        expect($rule->matches($uncategorized))->toBeFalse();
    });
});

describe('Rule Priority and Stop Processing', function () {
    it('respects stop_processing flag', function () {
        $category1 = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Priority Category',
        ]);

        $category2 = Category::factory()->expense()->create([
            'user_id' => $this->user->id,
            'name' => 'Secondary Category',
        ]);

        // High priority rule with stop_processing
        TransactionRule::factory()->withCategoryAction($category1->id)->create([
            'user_id' => $this->user->id,
            'priority' => 100,
            'stop_processing' => true,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 50,
                ],
            ],
        ]);

        // Lower priority rule that would also match
        TransactionRule::factory()->withCategoryAction($category2->id)->create([
            'user_id' => $this->user->id,
            'priority' => 50,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 25,
                ],
            ],
        ]);

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => 75, // Matches both rules
            'description' => 'Test Purchase',
            'date' => now(),
        ]);

        // Only the first rule should have been applied
        expect($transaction->category_id)->toBe($category1->id);
    });

    it('applies multiple rules when stop_processing is false', function () {
        // First rule adds a tag
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'priority' => 100,
            'stop_processing' => false,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Coffee',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_notes',
                    'notes' => 'beverage',
                ],
            ],
        ]);

        // Second rule adds another tag
        TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'priority' => 50,
            'stop_processing' => false,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => 'less_than',
                    'value' => 10,
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_notes',
                    'notes' => 'small-purchase',
                ],
            ],
        ]);

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => 5,
            'description' => 'Coffee Shop',
            'date' => now(),
        ]);

        // Second rule should have been applied (last one wins for notes)
        expect($transaction->notes)->toBe('small-purchase');
    });
});

describe('Rule Statistics', function () {
    it('tracks times_applied correctly', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'times_applied' => 0,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Test',
                ],
            ],
            'actions' => [
                [
                    'type' => 'set_notes',
                    'notes' => 'test',
                ],
            ],
        ]);

        // Create and apply rule to multiple transactions
        for ($i = 0; $i < 5; $i++) {
            $transaction = Transaction::factory()->make([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'description' => 'Test Transaction '.$i,
            ]);
            $transaction->saveQuietly();

            $rule->apply($transaction);
        }

        $rule->refresh();
        expect($rule->times_applied)->toBe(5);
        expect($rule->last_applied_at)->not->toBeNull();
    });

    it('updates last_applied_at timestamp', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'last_applied_at' => null,
            'conditions' => [
                [
                    'field' => 'description',
                    'operator' => 'contains',
                    'value' => 'Test',
                ],
            ],
        ]);

        $transaction = Transaction::factory()->make([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Test Transaction',
        ]);
        $transaction->saveQuietly();

        $beforeApply = now();
        $rule->apply($transaction);
        $afterApply = now();

        $rule->refresh();
        expect($rule->last_applied_at)->not->toBeNull();
        expect($rule->last_applied_at->timestamp)->toBeGreaterThanOrEqual($beforeApply->timestamp);
        expect($rule->last_applied_at->timestamp)->toBeLessThanOrEqual($afterApply->timestamp);
    });
});

describe('Rule Scopes', function () {
    it('active scope returns only active rules', function () {
        TransactionRule::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        TransactionRule::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);

        $activeRules = TransactionRule::where('user_id', $this->user->id)->active()->get();
        expect($activeRules)->toHaveCount(3);
        expect($activeRules->every(fn ($rule) => $rule->is_active === true))->toBeTrue();
    });

    it('active scope orders by priority descending', function () {
        $lowPriority = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'priority' => 10,
        ]);

        $highPriority = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'priority' => 100,
        ]);

        $mediumPriority = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'priority' => 50,
        ]);

        $rules = TransactionRule::where('user_id', $this->user->id)->active()->get();

        expect($rules[0]->id)->toBe($highPriority->id);
        expect($rules[1]->id)->toBe($mediumPriority->id);
        expect($rules[2]->id)->toBe($lowPriority->id);
    });
});
