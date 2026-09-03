<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateRecurringTransactionTool extends Tool
{
    protected string $description = 'Create a recurring transaction schedule. The scheduler generates real transactions from it daily, or use process-due-recurring-transactions-tool to run it on demand.';

    public function __construct(private RecurringTransactionService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(RecurringTransactionService::rules(), [
            'type.required' => 'Please specify the type (income, expense, or transfer).',
            'amount.required' => 'Please specify the recurring amount.',
            'description.required' => 'Please provide a description for the schedule.',
            'frequency.required' => 'Please specify the frequency (daily, weekly, monthly, or annual).',
        ]);

        try {
            $recurring = $this->service->create($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        $recurring->load(['account', 'toAccount', 'category']);

        return Response::structured([
            'message' => "Recurring transaction '{$recurring->description}' created successfully.",
            'recurring_transaction' => $this->service->detail($recurring),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return self::recurringSchema($schema);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public static function recurringSchema(JsonSchema $schema, bool $forUpdate = false): array
    {
        $type = $schema->string()
            ->enum(['income', 'expense', 'transfer'])
            ->description('The transaction type');

        $amount = $schema->number()
            ->description('The amount in decimal format (e.g., 150.50)');

        $description = $schema->string()
            ->description('Description used for each generated transaction');

        $frequency = $schema->string()
            ->enum(RecurringTransactionService::FREQUENCIES)
            ->description('How often the transaction recurs');

        return [
            'type' => $forUpdate ? $type : $type->required(),
            'amount' => $forUpdate ? $amount : $amount->required(),
            'description' => $forUpdate ? $description : $description->required(),
            'frequency' => $forUpdate ? $frequency : $frequency->required(),
            'account_id' => $schema->integer()
                ->description('The account ID (the source account for transfers)'),
            'from_account_id' => $schema->integer()
                ->description('Alias for account_id when creating a transfer schedule'),
            'to_account_id' => $schema->integer()
                ->description('Destination account ID (required for transfers)'),
            'category_id' => $schema->integer()
                ->description('Optional category ID'),
            'interval' => $schema->integer()
                ->description('Repeat every N periods, e.g. 2 with frequency "weekly" is fortnightly')
                ->default(1),
            'day_of_week' => $schema->integer()
                ->description('For weekly schedules: 0=Sunday through 6=Saturday'),
            'day_of_month' => $schema->integer()
                ->description('For monthly and annual schedules: 1-31. Day 31 always lands on the last day of the month.'),
            'month_of_year' => $schema->integer()
                ->description('For annual schedules: 1-12'),
            'start_date' => $schema->string()
                ->description('Date the recurrence starts (YYYY-MM-DD). Defaults to today.'),
            'next_date' => $schema->string()
                ->description('Date of the next occurrence (YYYY-MM-DD). Defaults to start_date.'),
            'end_date' => $schema->string()
                ->description('Optional date the recurrence stops (YYYY-MM-DD)'),
            'is_active' => $schema->boolean()
                ->description('Whether the schedule is active')
                ->default(true),
            'auto_process' => $schema->boolean()
                ->description('Whether the daily scheduler generates transactions automatically. When false, the schedule only runs when processed on demand.')
                ->default(true),
            'notes' => $schema->string()
                ->description('Optional notes'),
        ];
    }
}
