<?php

namespace App\Mcp\Tools\Payees;

use App\Models\Category;
use App\Models\Payee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdatePayeeTool extends Tool
{
    protected string $description = 'Update an existing payee.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'default_category_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'id.required' => 'Payee ID is required.',
        ]);

        $payee = Payee::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $payee) {
            return Response::error('Payee not found or access denied.');
        }

        if (isset($validated['name']) && $validated['name'] !== $payee->name) {
            $exists = Payee::where('user_id', $request->user()->id)
                ->where('name', $validated['name'])
                ->where('id', '!=', $payee->id)
                ->exists();

            if ($exists) {
                return Response::error("A payee with the name '{$validated['name']}' already exists.");
            }

            $payee->name = $validated['name'];
        }

        if (array_key_exists('default_category_id', $validated)) {
            if ($validated['default_category_id'] !== null) {
                $categoryExists = Category::where('user_id', $request->user()->id)
                    ->where('id', $validated['default_category_id'])
                    ->exists();

                if (! $categoryExists) {
                    return Response::error('The specified default category does not exist.');
                }
            }

            $payee->default_category_id = $validated['default_category_id'];
        }

        if (isset($validated['is_active'])) {
            $payee->is_active = $validated['is_active'];
        }

        $payee->save();
        $payee->load('defaultCategory');

        return Response::structured([
            'message' => "Payee '{$payee->name}' updated successfully.",
            'payee' => [
                'id' => $payee->id,
                'name' => $payee->name,
                'default_category' => $payee->defaultCategory ? [
                    'id' => $payee->defaultCategory->id,
                    'name' => $payee->defaultCategory->name,
                ] : null,
                'is_active' => $payee->is_active,
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
                ->description('The payee ID to update')
                ->required(),
            'name' => $schema->string()
                ->description('The new payee name'),
            'default_category_id' => $schema->integer()
                ->description('Default category ID for transactions with this payee'),
            'is_active' => $schema->boolean()
                ->description('Whether the payee is active'),
        ];
    }
}
