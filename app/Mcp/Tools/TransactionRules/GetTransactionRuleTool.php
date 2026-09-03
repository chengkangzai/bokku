<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Models\TransactionRule;
use App\Services\TransactionRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTransactionRuleTool extends Tool
{
    protected string $description = 'Get a transaction rule by ID, including its conditions, actions, and how many transactions it has been applied to.';

    public function __construct(private TransactionRuleService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Transaction rule ID is required.',
        ]);

        $rule = TransactionRule::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $rule) {
            return Response::error('Transaction rule not found or access denied.');
        }

        return Response::structured([
            'transaction_rule' => [
                ...$this->service->summarize($rule),
                'applied_transactions_count' => $rule->appliedTransactions()->count(),
                'created_at' => $rule->created_at->toIso8601String(),
                'updated_at' => $rule->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction rule ID')
                ->required(),
        ];
    }
}
