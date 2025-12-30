<?php

namespace App\Mcp\Tools\Categories;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateCategoryTool extends Tool
{
    protected string $description = 'Update an existing category.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(['income', 'expense'])],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'id.required' => 'Please specify the category ID to update.',
        ]);

        $category = Category::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $category) {
            return Response::error('Category not found or access denied.');
        }

        $updates = [];

        if (isset($validated['name'])) {
            $updates['name'] = $validated['name'];
        }

        if (isset($validated['type'])) {
            $updates['type'] = $validated['type'];
        }

        if (array_key_exists('color', $validated)) {
            $updates['color'] = $validated['color'];
        }

        if (array_key_exists('icon', $validated)) {
            $updates['icon'] = $validated['icon'];
        }

        if (isset($validated['sort_order'])) {
            $updates['sort_order'] = $validated['sort_order'];
        }

        $category->update($updates);

        return Response::structured([
            'message' => "Category '{$category->name}' updated successfully.",
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
                'sort_order' => $category->sort_order,
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
                ->description('The category ID to update')
                ->required(),
            'name' => $schema->string()
                ->description('The new category name'),
            'type' => $schema->string()
                ->enum(['income', 'expense'])
                ->description('The new category type'),
            'color' => $schema->string()
                ->description('New hex color code'),
            'icon' => $schema->string()
                ->description('New icon name or identifier'),
            'sort_order' => $schema->integer()
                ->description('New sort order'),
        ];
    }
}
