<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Models\TransactionRule;
use App\Services\TransactionRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateTransactionRuleTool extends Tool
{
    protected string $description = 'Update a transaction rule. Only the fields you provide are changed; supplying conditions or actions replaces them entirely.';

    public function __construct(private TransactionRuleService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            ...TransactionRuleService::rules(forUpdate: true),
        ], [
            'id.required' => 'Transaction rule ID is required.',
        ]);

        $rule = TransactionRule::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $rule) {
            return Response::error('Transaction rule not found or access denied.');
        }

        try {
            $this->service->update($rule, array_filter(
                $validated,
                fn (string $key) => $key !== 'id',
                ARRAY_FILTER_USE_KEY,
            ));
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'message' => "Transaction rule '{$rule->name}' updated successfully.",
            'transaction_rule' => $this->service->summarize($rule),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction rule ID to update')
                ->required(),
            ...CreateTransactionRuleTool::ruleSchema($schema, forUpdate: true),
        ];
    }
}
