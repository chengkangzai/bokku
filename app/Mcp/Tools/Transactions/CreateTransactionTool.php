<?php

namespace App\Mcp\Tools\Transactions;

use App\Services\TransactionCreationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateTransactionTool extends Tool
{
    protected string $description = 'Create a new transaction (income, expense, or transfer). To create many at once, use bulk-create-transactions-tool instead.';

    public function __construct(private TransactionCreationService $creationService) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(TransactionCreationService::rules(), [
            'type.required' => 'Please specify the transaction type (income, expense, or transfer).',
            'amount.required' => 'Please specify the transaction amount.',
            'description.required' => 'Please provide a description for the transaction.',
            'date.required' => 'Please specify the transaction date.',
        ]);

        try {
            $transaction = $this->creationService->create($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'message' => 'Transaction created successfully.',
            'transaction' => $this->creationService->summarize($transaction),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return self::transactionSchema($schema);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public static function transactionSchema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['income', 'expense', 'transfer'])
                ->description('The transaction type')
                ->required(),
            'amount' => $schema->number()
                ->description('The transaction amount in decimal format (e.g., 150.50)')
                ->required(),
            'description' => $schema->string()
                ->description('Description of the transaction')
                ->required(),
            'date' => $schema->string()
                ->description('The transaction date (YYYY-MM-DD)')
                ->required(),
            'account_id' => $schema->integer()
                ->description('The account ID (required for income/expense, not for transfers)'),
            'from_account_id' => $schema->integer()
                ->description('Source account ID (required for transfers)'),
            'to_account_id' => $schema->integer()
                ->description('Destination account ID (required for transfers)'),
            'category_id' => $schema->integer()
                ->description('Optional category ID'),
            'payee_id' => $schema->integer()
                ->description('Optional payee ID'),
            'reference' => $schema->string()
                ->description('Optional reference number'),
            'notes' => $schema->string()
                ->description('Optional notes'),
            'is_reconciled' => $schema->boolean()
                ->description('Whether the transaction is reconciled')
                ->default(false),
            'tags' => $schema->array()
                ->description('Optional array of tag names to attach to the transaction'),
        ];
    }
}
