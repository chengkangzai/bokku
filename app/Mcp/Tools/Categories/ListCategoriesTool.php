<?php

namespace App\Mcp\Tools\Categories;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListCategoriesTool extends Tool
{
    protected string $description = 'List all categories for the authenticated user, optionally filtered by type.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['income', 'expense'])],
        ]);

        $query = Category::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('name');

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $categories = $query->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
                'sort_order' => $category->sort_order,
            ]);

        return Response::structured([
            'categories' => $categories->toArray(),
            'count' => $categories->count(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['income', 'expense'])
                ->description('Filter by category type'),
        ];
    }
}
