<?php

namespace App\Mcp\Tools\Categories;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteCategoryTool extends Tool
{
    protected string $description = 'Delete a category. Transactions using this category will have their category set to null.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Please specify the category ID to delete.',
        ]);

        $category = Category::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $category) {
            return Response::error('Category not found or access denied.');
        }

        $transactionCount = $category->transactions()->count();

        Transaction::where('category_id', $category->id)
            ->update(['category_id' => null]);

        $categoryName = $category->name;
        $category->delete();

        $message = "Category '{$categoryName}' deleted successfully.";
        if ($transactionCount > 0) {
            $message .= " {$transactionCount} transaction(s) have been uncategorized.";
        }

        return Response::structured([
            'message' => $message,
            'deleted' => true,
            'affected_transactions' => $transactionCount,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The category ID to delete')
                ->required(),
        ];
    }
}
