<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Models\TransactionRule;
use App\Services\TransactionRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTransactionRulesTool extends Tool
{
    protected string $description = 'List transaction rules for the authenticated user, ordered by the priority in which they run.';

    public function __construct(private TransactionRuleService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'apply_to' => ['nullable', 'string', Rule::in(TransactionRuleService::APPLY_TO)],
        ]);

        $query = TransactionRule::where('user_id', $request->user()->id)
            ->orderBy('priority', 'desc')
            ->orderBy('id');

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        if (isset($validated['apply_to'])) {
            $query->where('apply_to', $validated['apply_to']);
        }

        $rules = $query->get()->map(fn (TransactionRule $rule) => $this->service->summarize($rule));

        return Response::structured([
            'transaction_rules' => $rules->toArray(),
            'count' => $rules->count(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_active' => $schema->boolean()
                ->description('Filter by active status'),
            'apply_to' => $schema->string()
                ->enum(TransactionRuleService::APPLY_TO)
                ->description('Filter by the transaction type the rule applies to'),
        ];
    }
}
