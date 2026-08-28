<?php

namespace App\Mcp\Tools\Transactions;

use App\Services\TransactionCreationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class BulkCreateTransactionsTool extends Tool
{
    public const int MAX_BATCH_SIZE = 100;

    protected string $description = 'Create multiple transactions in one call (income, expense, or transfer). All-or-nothing: if any item is invalid, nothing is created and the error names the failing item index. Max 100 items per call.';

    public function __construct(private TransactionCreationService $creationService) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transactions' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH_SIZE],
            ...TransactionCreationService::rules('transactions.*.'),
        ], [
            'transactions.required' => 'Please provide an array of transactions to create.',
            'transactions.min' => 'Please provide at least one transaction.',
            'transactions.max' => 'You can create at most '.self::MAX_BATCH_SIZE.' transactions per call.',
        ]);

        $user = $request->user();

        try {
            $created = DB::transaction(function () use ($user, $validated): array {
                $results = [];

                foreach ($validated['transactions'] as $index => $item) {
                    try {
                        $transaction = $this->creationService->create($user, $item);
                    } catch (InvalidArgumentException $exception) {
                        throw new InvalidArgumentException("Item {$index}: {$exception->getMessage()}", previous: $exception);
                    }

                    $results[] = ['index' => $index, ...$this->creationService->summarize($transaction)];
                }

                return $results;
            });
        } catch (InvalidArgumentException $exception) {
            return Response::error("{$exception->getMessage()} No transactions were created.");
        }

        $count = count($created);

        return Response::structured([
            'message' => "{$count} transaction(s) created successfully.",
            'count' => $count,
            'transactions' => $created,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array(
                $schema->object(CreateTransactionTool::transactionSchema($schema))
            )
                ->description('Array of transactions to create. Each item accepts the same fields as create-transaction-tool.')
                ->min(1)
                ->max(self::MAX_BATCH_SIZE)
                ->required(),
        ];
    }
}
