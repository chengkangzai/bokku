<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Services\TransactionRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateTransactionRuleTool extends Tool
{
    protected string $description = 'Create a transaction rule that automatically categorizes, tags, or annotates newly created transactions. All conditions must match for the actions to run.';

    public function __construct(private TransactionRuleService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(TransactionRuleService::rules(), [
            'name.required' => 'Please provide a name for the rule.',
            'conditions.required' => 'Please provide at least one condition.',
            'actions.required' => 'Please provide at least one action.',
        ]);

        try {
            $rule = $this->service->create($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'message' => "Transaction rule '{$rule->name}' created successfully. It applies to transactions created from now on; use apply-transaction-rules-tool to backfill existing ones.",
            'transaction_rule' => $this->service->summarize($rule),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return self::ruleSchema($schema);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public static function ruleSchema(JsonSchema $schema, bool $forUpdate = false): array
    {
        $name = $schema->string()
            ->description('A name for the rule');

        $conditions = $schema->array()
            ->description('Conditions that must ALL match, each an object {field, operator, value}. Fields: description, amount, category_id, account_id, date. Operators for description: equals, not_equals, contains, not_contains, starts_with, ends_with, regex. For amount and date: equals, not_equals, greater_than, less_than, greater_than_or_equal, less_than_or_equal. For category_id and account_id: equals, not_equals. Amount values are decimals (e.g. 100.50), date values are YYYY-MM-DD.');

        $actions = $schema->array()
            ->description('Actions to run when the conditions match, each an object {type, ...}. Types: set_category (with category_id), set_account (with account_id), set_notes (with notes), add_tag (with tag).');

        return [
            'name' => $forUpdate ? $name : $name->required(),
            'conditions' => $forUpdate ? $conditions : $conditions->required(),
            'actions' => $forUpdate ? $actions : $actions->required(),
            'description' => $schema->string()
                ->description('Optional description of what the rule is for'),
            'apply_to' => $schema->string()
                ->enum(TransactionRuleService::APPLY_TO)
                ->description('Which transaction types the rule applies to')
                ->default('all'),
            'priority' => $schema->integer()
                ->description('Higher priority rules run first')
                ->default(0),
            'is_active' => $schema->boolean()
                ->description('Whether the rule is active')
                ->default(true),
            'stop_processing' => $schema->boolean()
                ->description('Stop applying any lower priority rules once this one matches')
                ->default(false),
        ];
    }
}
