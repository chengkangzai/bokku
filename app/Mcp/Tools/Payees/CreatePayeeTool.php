<?php

namespace App\Mcp\Tools\Payees;

use App\Models\Category;
use App\Models\Payee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreatePayeeTool extends Tool
{
    protected string $description = 'Create a new payee/merchant.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_category_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Please provide a name for the payee.',
        ]);

        $exists = Payee::where('user_id', $request->user()->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return Response::error("A payee with the name '{$validated['name']}' already exists.");
        }

        if (isset($validated['default_category_id'])) {
            $categoryExists = Category::where('user_id', $request->user()->id)
                ->where('id', $validated['default_category_id'])
                ->exists();

            if (! $categoryExists) {
                return Response::error('The specified default category does not exist.');
            }
        }

        $payee = Payee::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'default_category_id' => $validated['default_category_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $payee->load('defaultCategory');

        return Response::structured([
            'message' => "Payee '{$payee->name}' created successfully.",
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
            'name' => $schema->string()
                ->description('The payee/merchant name')
                ->required(),
            'default_category_id' => $schema->integer()
                ->description('Default category ID for transactions with this payee'),
            'is_active' => $schema->boolean()
                ->description('Whether the payee is active')
                ->default(true),
        ];
    }
}
