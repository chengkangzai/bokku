<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\TransactionRules\ApplyTransactionRulesTool;
use App\Mcp\Tools\TransactionRules\CreateTransactionRuleTool;
use App\Mcp\Tools\TransactionRules\DeleteTransactionRuleTool;
use App\Mcp\Tools\TransactionRules\GetTransactionRuleTool;
use App\Mcp\Tools\TransactionRules\ListTransactionRulesTool;
use App\Mcp\Tools\TransactionRules\TestTransactionRuleTool;
use App\Mcp\Tools\TransactionRules\UpdateTransactionRuleTool;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    $this->category = Category::factory()->expense()->create(['user_id' => $this->user->id, 'name' => 'Coffee']);
});

describe('ListTransactionRulesTool', function () {
    it('returns only the users rules, highest priority first', function () {
        TransactionRule::factory()->create(['user_id' => $this->user->id, 'name' => 'Low Priority', 'priority' => 1]);
        TransactionRule::factory()->create(['user_id' => $this->user->id, 'name' => 'High Priority', 'priority' => 99]);
        TransactionRule::factory()->create(['user_id' => $this->otherUser->id, 'name' => 'Not Mine']);

        $response = BokkuServer::actingAs($this->user)->tool(ListTransactionRulesTool::class);

        $response->assertOk()
            ->assertSee('"count":2')
            ->assertDontSee('Not Mine');

        $names = TransactionRule::where('user_id', $this->user->id)
            ->orderBy('priority', 'desc')
            ->pluck('name')
            ->all();

        expect($names[0])->toBe('High Priority');
    });

    it('filters by active status', function () {
        TransactionRule::factory()->create(['user_id' => $this->user->id, 'name' => 'Active Rule']);
        TransactionRule::factory()->inactive()->create(['user_id' => $this->user->id, 'name' => 'Inactive Rule']);

        BokkuServer::actingAs($this->user)->tool(ListTransactionRulesTool::class, ['is_active' => true])
            ->assertOk()
            ->assertSee('Active Rule')
            ->assertDontSee('Inactive Rule');
    });
});

describe('GetTransactionRuleTool', function () {
    it('returns the rule with its conditions and actions', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Starbucks Rule',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks']],
        ]);

        BokkuServer::actingAs($this->user)->tool(GetTransactionRuleTool::class, ['id' => $rule->id])
            ->assertOk()
            ->assertSee('Starbucks Rule')
            ->assertSee('applied_transactions_count');
    });

    it('does not expose another users rule', function () {
        $rule = TransactionRule::factory()->create(['user_id' => $this->otherUser->id]);

        BokkuServer::actingAs($this->user)->tool(GetTransactionRuleTool::class, ['id' => $rule->id])
            ->assertHasErrors();
    });
});

describe('CreateTransactionRuleTool', function () {
    it('creates a rule that categorizes by description', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'Coffee Shops',
            'conditions' => [
                ['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks'],
            ],
            'actions' => [
                ['type' => 'set_category', 'category_id' => $this->category->id],
            ],
            'apply_to' => 'expense',
            'priority' => 10,
        ]);

        $response->assertOk()->assertSee('Coffee Shops');

        $this->assertDatabaseHas('transaction_rules', [
            'user_id' => $this->user->id,
            'name' => 'Coffee Shops',
            'apply_to' => 'expense',
            'priority' => 10,
        ]);
    });

    it('stores amount condition values as decimals', function () {
        BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'Large Expenses',
            'conditions' => [
                ['field' => 'amount', 'operator' => 'greater_than', 'value' => '250.50'],
            ],
            'actions' => [['type' => 'add_tag', 'tag' => 'large']],
        ])->assertOk();

        $rule = TransactionRule::where('name', 'Large Expenses')->first();

        expect($rule->conditions[0]['value'])->toBe(250.5);
    });

    it('rejects an operator that the field does not support', function () {
        $response = BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'Bad Operator',
            'conditions' => [
                ['field' => 'amount', 'operator' => 'contains', 'value' => '100'],
            ],
            'actions' => [['type' => 'add_tag', 'tag' => 'nope']],
        ]);

        $response->assertHasErrors();
        $this->assertDatabaseMissing('transaction_rules', ['name' => 'Bad Operator']);
    });

    it('rejects an invalid regex pattern', function () {
        BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'Bad Regex',
            'conditions' => [
                ['field' => 'description', 'operator' => 'regex', 'value' => '([unclosed'],
            ],
            'actions' => [['type' => 'add_tag', 'tag' => 'nope']],
        ])->assertHasErrors();

        $this->assertDatabaseMissing('transaction_rules', ['name' => 'Bad Regex']);
    });

    it('rejects a set_category action for another users category', function () {
        $otherCategory = Category::factory()->expense()->create(['user_id' => $this->otherUser->id]);

        BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'Cross Tenant',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'x']],
            'actions' => [['type' => 'set_category', 'category_id' => $otherCategory->id]],
        ])->assertHasErrors();

        $this->assertDatabaseMissing('transaction_rules', ['name' => 'Cross Tenant']);
    });

    it('rejects a set_notes action with no notes', function () {
        BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'Missing Notes',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'x']],
            'actions' => [['type' => 'set_notes']],
        ])->assertHasErrors();
    });

    it('requires at least one condition', function () {
        BokkuServer::actingAs($this->user)->tool(CreateTransactionRuleTool::class, [
            'name' => 'No Conditions',
            'conditions' => [],
            'actions' => [['type' => 'add_tag', 'tag' => 'x']],
        ])->assertHasErrors();
    });
});

describe('UpdateTransactionRuleTool', function () {
    it('replaces conditions when provided', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Old']],
        ]);

        BokkuServer::actingAs($this->user)->tool(UpdateTransactionRuleTool::class, [
            'id' => $rule->id,
            'conditions' => [['field' => 'description', 'operator' => 'starts_with', 'value' => 'New']],
        ])->assertOk();

        expect($rule->fresh()->conditions)->toBe([
            ['field' => 'description', 'operator' => 'starts_with', 'value' => 'New'],
        ]);
    });

    it('leaves conditions untouched when not provided', function () {
        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Keep']],
        ]);

        BokkuServer::actingAs($this->user)->tool(UpdateTransactionRuleTool::class, [
            'id' => $rule->id,
            'is_active' => false,
        ])->assertOk();

        expect($rule->fresh()->conditions[0]['value'])->toBe('Keep');
        expect($rule->fresh()->is_active)->toBeFalse();
    });

    it('cannot update another users rule', function () {
        $rule = TransactionRule::factory()->create(['user_id' => $this->otherUser->id]);

        BokkuServer::actingAs($this->user)->tool(UpdateTransactionRuleTool::class, [
            'id' => $rule->id,
            'name' => 'Hijacked',
        ])->assertHasErrors();

        $this->assertDatabaseMissing('transaction_rules', ['name' => 'Hijacked']);
    });
});

describe('DeleteTransactionRuleTool', function () {
    it('deletes the rule', function () {
        $rule = TransactionRule::factory()->create(['user_id' => $this->user->id]);

        BokkuServer::actingAs($this->user)->tool(DeleteTransactionRuleTool::class, ['id' => $rule->id])
            ->assertOk();

        $this->assertDatabaseMissing('transaction_rules', ['id' => $rule->id]);
    });

    it('cannot delete another users rule', function () {
        $rule = TransactionRule::factory()->create(['user_id' => $this->otherUser->id]);

        BokkuServer::actingAs($this->user)->tool(DeleteTransactionRuleTool::class, ['id' => $rule->id])
            ->assertHasErrors();

        $this->assertDatabaseHas('transaction_rules', ['id' => $rule->id]);
    });
});

describe('TestTransactionRuleTool', function () {
    it('previews matches for a saved rule without changing anything', function () {
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Starbucks Bangsar',
        ]);
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Petrol Station',
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks']],
            'actions' => [['type' => 'set_category', 'category_id' => $this->category->id]],
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(TestTransactionRuleTool::class, ['id' => $rule->id]);

        $response->assertOk()
            ->assertSee('"matched":1')
            ->assertSee('Starbucks Bangsar')
            ->assertDontSee('Petrol Station');

        expect($rule->fresh()->times_applied)->toBe(0);
    });

    it('previews an unsaved definition without creating a rule', function () {
        Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Netflix Subscription',
        ]);

        BokkuServer::actingAs($this->user)->tool(TestTransactionRuleTool::class, [
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Netflix']],
        ])->assertOk()->assertSee('"matched":1');

        expect(TransactionRule::count())->toBe(0);
    });

    it('requires either an id or conditions', function () {
        BokkuServer::actingAs($this->user)->tool(TestTransactionRuleTool::class)->assertHasErrors();
    });
});

describe('ApplyTransactionRulesTool', function () {
    it('backfills a category onto existing transactions', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Starbucks KLCC',
            'category_id' => null,
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'apply_to' => 'expense',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks']],
            'actions' => [['type' => 'set_category', 'category_id' => $this->category->id]],
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class, [
            'rule_id' => $rule->id,
        ]);

        $response->assertOk()->assertSee('"affected_count":1');

        expect($transaction->fresh()->category_id)->toBe($this->category->id);
    });

    it('changes nothing on a dry run', function () {
        $transaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Starbucks KLCC',
            'category_id' => null,
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'apply_to' => 'expense',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks']],
            'actions' => [['type' => 'set_category', 'category_id' => $this->category->id]],
        ]);

        BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class, [
            'rule_id' => $rule->id,
            'dry_run' => true,
        ])->assertOk()->assertSee('"affected_count":1');

        expect($transaction->fresh()->category_id)->toBeNull();
        expect($rule->fresh()->times_applied)->toBe(0);
    });

    it('only touches transactions the rule matches', function () {
        $matching = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Starbucks KLCC',
            'category_id' => null,
        ]);
        $other = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'description' => 'Groceries',
            'category_id' => null,
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'apply_to' => 'expense',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks']],
            'actions' => [['type' => 'set_category', 'category_id' => $this->category->id]],
        ]);

        BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class, ['rule_id' => $rule->id])
            ->assertOk();

        expect($matching->fresh()->category_id)->toBe($this->category->id);
        expect($other->fresh()->category_id)->toBeNull();
    });

    it('refuses to apply an inactive rule', function () {
        $rule = TransactionRule::factory()->inactive()->create(['user_id' => $this->user->id]);

        BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class, ['rule_id' => $rule->id])
            ->assertHasErrors();
    });

    it('cannot apply another users rule', function () {
        $rule = TransactionRule::factory()->create(['user_id' => $this->otherUser->id]);

        BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class, ['rule_id' => $rule->id])
            ->assertHasErrors();
    });

    it('never touches another users transactions', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $otherTransaction = Transaction::factory()->expense()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
            'description' => 'Starbucks KLCC',
            'category_id' => null,
        ]);

        $rule = TransactionRule::factory()->create([
            'user_id' => $this->user->id,
            'apply_to' => 'expense',
            'conditions' => [['field' => 'description', 'operator' => 'contains', 'value' => 'Starbucks']],
            'actions' => [['type' => 'set_category', 'category_id' => $this->category->id]],
        ]);

        BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class, ['rule_id' => $rule->id])
            ->assertOk()
            ->assertSee('"affected_count":0');

        expect($otherTransaction->fresh()->category_id)->toBeNull();
    });

    it('errors when the user has no active rules', function () {
        BokkuServer::actingAs($this->user)->tool(ApplyTransactionRulesTool::class)->assertHasErrors();
    });
});
