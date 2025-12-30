<?php

namespace App\Mcp\Tools\Categories;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCategoryTool extends Tool
{
    protected string $description = 'Get details of a specific category by ID, including monthly spending statistics.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Please specify the category ID.',
        ]);

        $category = Category::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $category) {
            return Response::error('Category not found or access denied.');
        }

        $monthlySpending = $category->transactions()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount') / 100;

        $transactionCount = $category->transactions()->count();

        return Response::structured([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
                'sort_order' => $category->sort_order,
                'created_at' => $category->created_at->toIso8601String(),
                'updated_at' => $category->updated_at->toIso8601String(),
            ],
            'statistics' => [
                'monthly_total' => $monthlySpending,
                'transaction_count' => $transactionCount,
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
                ->description('The category ID')
                ->required(),
        ];
    }
}
