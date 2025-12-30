<?php

namespace App\Mcp\Tools\Accounts;

use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListAccountsTool extends Tool
{
    protected string $description = 'List all financial accounts for the authenticated user with their current balances.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $accounts = Account::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'balance' => $account->balance,
                'formatted_balance' => $account->formatted_balance,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
            ]);

        return Response::structured([
            'accounts' => $accounts->toArray(),
            'count' => $accounts->count(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
