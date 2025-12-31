<?php

namespace App\Mcp\Tools\Budgets;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateBudgetTool extends Tool
{
    protected string $description = 'Create a new budget for a category.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'period' => ['required', 'string', Rule::in(['weekly', 'monthly', 'annual'])],
            'start_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'auto_rollover' => ['nullable', 'boolean'],
        ], [
            'category_id.required' => 'Category ID is required.',
            'amount.required' => 'Budget amount is required.',
            'amount.min' => 'Budget amount must be at least 0.01.',
            'period.required' => 'Budget period is required.',
            'period.in' => 'Period must be weekly, monthly, or annual.',
        ]);

        $category = Category::where('user_id', $request->user()->id)
            ->find($validated['category_id']);

        if (! $category) {
            return Response::error('Category not found or access denied.');
        }

        $exists = Budget::where('user_id', $request->user()->id)
            ->where('category_id', $validated['category_id'])
            ->exists();

        if ($exists) {
            return Response::error("A budget for category '{$category->name}' already exists.");
        }

        $budget = Budget::create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'period' => $validated['period'],
            'start_date' => $validated['start_date'] ?? now()->toDateString(),
            'is_active' => $validated['is_active'] ?? true,
            'auto_rollover' => $validated['auto_rollover'] ?? false,
        ]);

        $budget->load('category');

        return Response::structured([
            'message' => "Budget for '{$budget->category->name}' created successfully.",
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
            'category_id' => $schema->integer()
                ->description('The category ID to budget for')
                ->required(),
            'amount' => $schema->number()
                ->description('The budget amount in decimal format (e.g., 500.00)')
                ->required(),
            'period' => $schema->string()
                ->enum(['weekly', 'monthly', 'annual'])
                ->description('The budget period')
                ->required(),
            'start_date' => $schema->string()
                ->description('Start date (YYYY-MM-DD). Defaults to today.'),
            'is_active' => $schema->boolean()
                ->description('Whether the budget is active')
                ->default(true),
            'auto_rollover' => $schema->boolean()
                ->description('Whether unused budget rolls over to next period')
                ->default(false),
        ];
    }
}
