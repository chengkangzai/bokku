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
class ListPayeesTool extends Tool
{
    protected string $description = 'List all payees/merchants for the authenticated user.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $query = Payee::where('user_id', $request->user()->id)
            ->with('defaultCategory')
            ->withCount('transactions')
            ->orderBy('name');

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        $payees = $query->get()
            ->map(fn (Payee $payee) => [
                'id' => $payee->id,
                'name' => $payee->name,
                'type' => $payee->type?->value,
                'default_category' => $payee->defaultCategory ? [
                    'id' => $payee->defaultCategory->id,
                    'name' => $payee->defaultCategory->name,
                ] : null,
                'total_amount' => $payee->total_amount,
                'is_active' => $payee->is_active,
                'transaction_count' => $payee->transactions_count,
            ]);

        return Response::structured([
            'payees' => $payees->toArray(),
            'count' => $payees->count(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_active' => $schema->boolean()
                ->description('Filter by active status'),
        ];
    }
}
