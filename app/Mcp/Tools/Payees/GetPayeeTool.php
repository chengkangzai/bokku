<?php

namespace App\Mcp\Tools\Payees;

use App\Models\Payee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetPayeeTool extends Tool
{
    protected string $description = 'Get details of a specific payee by ID.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Payee ID is required.',
        ]);

        $payee = Payee::where('user_id', $request->user()->id)
            ->with('defaultCategory')
            ->withCount('transactions')
            ->find($validated['id']);

        if (! $payee) {
            return Response::error('Payee not found or access denied.');
        }

        return Response::structured([
            'payee' => [
                'id' => $payee->id,
                'name' => $payee->name,
                'type' => $payee->type?->value,
                'default_category' => $payee->defaultCategory ? [
                    'id' => $payee->defaultCategory->id,
                    'name' => $payee->defaultCategory->name,
                    'type' => $payee->defaultCategory->type,
                ] : null,
                'notes' => $payee->notes,
                'total_amount' => $payee->total_amount,
                'is_active' => $payee->is_active,
                'transaction_count' => $payee->transactions_count,
                'created_at' => $payee->created_at->toIso8601String(),
                'updated_at' => $payee->updated_at->toIso8601String(),
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
                ->description('The payee ID')
                ->required(),
        ];
    }
}
