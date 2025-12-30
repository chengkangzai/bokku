<?php

namespace App\Mcp\Tools\Accounts;

use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteAccountTool extends Tool
{
    protected string $description = 'Delete an account. Only accounts with no transactions can be deleted.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Please specify the account ID to delete.',
        ]);

        $account = Account::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $account) {
            return Response::error('Account not found or access denied.');
        }

        if ($account->transactions()->exists()) {
            return Response::error("Cannot delete account '{$account->name}' because it has transactions. Delete or move the transactions first.");
        }

        if ($account->transfersFrom()->exists() || $account->transfersTo()->exists()) {
            return Response::error("Cannot delete account '{$account->name}' because it has transfer transactions.");
        }

        $accountName = $account->name;
        $account->delete();

        return Response::structured([
            'message' => "Account '{$accountName}' deleted successfully.",
            'deleted' => true,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The account ID to delete')
                ->required(),
        ];
    }
}
