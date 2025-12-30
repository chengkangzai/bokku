<?php

namespace App\Mcp\Tools\Categories;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateCategoryTool extends Tool
{
    protected string $description = 'Create a new category.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Please provide a name for the category.',
            'type.required' => 'Please specify the category type (income or expense).',
            'type.in' => 'Category type must be either income or expense.',
        ]);

        $exists = Category::where('user_id', $request->user()->id)
            ->where('name', $validated['name'])
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
            return Response::error("A {$validated['type']} category with the name '{$validated['name']}' already exists.");
        }

        $category = Category::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'color' => $validated['color'] ?? '#808080',
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return Response::structured([
            'message' => "Category '{$category->name}' created successfully.",
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The category name')
                ->required(),
            'type' => $schema->string()
                ->enum(['income', 'expense'])
                ->description('The category type')
                ->required(),
            'color' => $schema->string()
                ->description('Hex color code (e.g., #FF5733)'),
            'icon' => $schema->string()
                ->description('Icon name or identifier'),
            'sort_order' => $schema->integer()
                ->description('Sort order for display')
                ->default(0),
        ];
    }
}
