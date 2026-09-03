<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Models\TransactionRule;
use App\Services\TransactionRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TestTransactionRuleTool extends Tool
{
    protected string $description = 'Preview which existing transactions a rule matches, without changing anything. Pass an id to test a saved rule, or pass conditions and apply_to to test a definition before creating it.';

    public function __construct(private TransactionRuleService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'conditions' => ['nullable', 'array', 'min:1'],
            'conditions.*.field' => ['required', 'string', Rule::in(TransactionRuleService::FIELDS)],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['required'],
            'apply_to' => ['nullable', 'string', Rule::in(TransactionRuleService::APPLY_TO)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! isset($validated['id']) && ! isset($validated['conditions'])) {
            return Response::error('Provide either an id of a saved rule, or a conditions array to test.');
        }

        try {
            $rule = isset($validated['id'])
                ? $this->findRule($request, $validated['id'])
                : $this->buildDraftRule($request, $validated);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $rule) {
            return Response::error('Transaction rule not found or access denied.');
        }

        $preview = $this->service->preview($rule, $validated['limit'] ?? 25);

        return Response::structured([
            'rule' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'apply_to' => $rule->apply_to,
                'conditions' => $rule->conditions,
                'actions' => $rule->actions,
                'is_saved' => $rule->exists,
            ],
            ...$preview,
        ]);
    }

    private function findRule(Request $request, int $id): ?TransactionRule
    {
        return TransactionRule::where('user_id', $request->user()->id)->find($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function buildDraftRule(Request $request, array $validated): TransactionRule
    {
        return $this->service->draft($request->user(), [
            'conditions' => $validated['conditions'],
            'apply_to' => $validated['apply_to'] ?? 'all',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID of a saved rule to test'),
            'conditions' => $schema->array()
                ->description('Conditions to test instead of a saved rule, each an object {field, operator, value}. Same shape as create-transaction-rule-tool.'),
            'apply_to' => $schema->string()
                ->enum(TransactionRuleService::APPLY_TO)
                ->description('Transaction types to test against when using inline conditions')
                ->default('all'),
            'limit' => $schema->integer()
                ->description('Maximum matching transactions to return')
                ->default(25),
        ];
    }
}
