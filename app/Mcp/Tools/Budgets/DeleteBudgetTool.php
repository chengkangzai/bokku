<?php

namespace App\Mcp\Tools\Budgets;

use App\Models\Budget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteBudgetTool extends Tool
{
    protected string $description = 'Delete a budget.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Budget ID is required.',
        ]);

        $budget = Budget::where('user_id', $request->user()->id)
            ->with('category')
            ->find($validated['id']);

        if (! $budget) {
            return Response::error('Budget not found or access denied.');
        }

        $categoryName = $budget->category?->name ?? 'Unknown';
        $budget->delete();

        return Response::structured([
            'message' => "Budget for '{$categoryName}' deleted successfully.",
            'deleted' => true,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The budget ID to delete')
                ->required(),
        ];
    }
}
