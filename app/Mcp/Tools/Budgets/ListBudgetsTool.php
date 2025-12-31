<?php

namespace App\Mcp\Tools\Budgets;

use App\Models\Budget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListBudgetsTool extends Tool
{
    protected string $description = 'List all budgets for the authenticated user with spending progress.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $query = Budget::where('user_id', $request->user()->id)
            ->with('category')
            ->orderBy('created_at', 'desc');

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $budgets = $query->get()
            ->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'category' => $budget->category ? [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                ] : null,
                'amount' => $budget->amount,
                'period' => $budget->period,
                'start_date' => $budget->start_date->toDateString(),
                'is_active' => $budget->is_active,
                'auto_rollover' => $budget->auto_rollover,
                'spent_amount' => $budget->getSpentAmount(),
                'remaining_amount' => $budget->getRemainingAmount(),
                'progress_percentage' => $budget->getProgressPercentage(),
                'status' => $budget->getStatus(),
            ]);

        return Response::structured([
            'budgets' => $budgets->toArray(),
            'count' => $budgets->count(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_active' => $schema->boolean()
                ->description('Filter by active status'),
            'category_id' => $schema->integer()
                ->description('Filter by category ID'),
        ];
    }
}
