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
class GetBudgetTool extends Tool
{
    protected string $description = 'Get details of a specific budget by ID.';

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

        return Response::structured([
            'budget' => [
                'id' => $budget->id,
                'category' => $budget->category ? [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'type' => $budget->category->type,
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
                'current_period_start' => $budget->getCurrentPeriodStart()->toDateString(),
                'current_period_end' => $budget->getCurrentPeriodEnd()->toDateString(),
                'created_at' => $budget->created_at->toIso8601String(),
                'updated_at' => $budget->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The budget ID')
                ->required(),
        ];
    }
}
