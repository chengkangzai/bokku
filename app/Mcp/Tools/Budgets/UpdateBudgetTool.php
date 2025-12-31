<?php

namespace App\Mcp\Tools\Budgets;

use App\Models\Budget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateBudgetTool extends Tool
{
    protected string $description = 'Update an existing budget.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'period' => ['nullable', 'string', Rule::in(['weekly', 'monthly', 'annual'])],
            'start_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'auto_rollover' => ['nullable', 'boolean'],
        ], [
            'id.required' => 'Budget ID is required.',
            'amount.min' => 'Budget amount must be at least 0.01.',
            'period.in' => 'Period must be weekly, monthly, or annual.',
        ]);

        $budget = Budget::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $budget) {
            return Response::error('Budget not found or access denied.');
        }

        if (isset($validated['amount'])) {
            $budget->amount = $validated['amount'];
        }

        if (isset($validated['period'])) {
            $budget->period = $validated['period'];
        }

        if (isset($validated['start_date'])) {
            $budget->start_date = $validated['start_date'];
        }

        if (isset($validated['is_active'])) {
            $budget->is_active = $validated['is_active'];
        }

        if (isset($validated['auto_rollover'])) {
            $budget->auto_rollover = $validated['auto_rollover'];
        }

        $budget->save();
        $budget->load('category');

        return Response::structured([
            'message' => "Budget for '{$budget->category->name}' updated successfully.",
            'budget' => [
                'id' => $budget->id,
                'category' => [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                ],
                'amount' => $budget->amount,
                'period' => $budget->period,
                'start_date' => $budget->start_date->toDateString(),
                'is_active' => $budget->is_active,
                'auto_rollover' => $budget->auto_rollover,
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
                ->description('The budget ID to update')
                ->required(),
            'amount' => $schema->number()
                ->description('The new budget amount in decimal format'),
            'period' => $schema->string()
                ->enum(['weekly', 'monthly', 'annual'])
                ->description('The new budget period'),
            'start_date' => $schema->string()
                ->description('New start date (YYYY-MM-DD)'),
            'is_active' => $schema->boolean()
                ->description('Whether the budget is active'),
            'auto_rollover' => $schema->boolean()
                ->description('Whether unused budget rolls over to next period'),
        ];
    }
}
