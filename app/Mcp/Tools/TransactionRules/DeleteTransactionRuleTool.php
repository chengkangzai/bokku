<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Models\TransactionRule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteTransactionRuleTool extends Tool
{
    protected string $description = 'Delete a transaction rule. Transactions it already categorized keep their values.';

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

        $name = $rule->name;
        $rule->delete();

        return Response::structured([
            'message' => "Transaction rule '{$name}' deleted successfully.",
            'deleted' => true,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction rule ID to delete')
                ->required(),
        ];
    }
}
