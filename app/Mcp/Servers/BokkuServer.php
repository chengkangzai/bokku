<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\AnalyzeSpendingPrompt;
use App\Mcp\Prompts\BudgetAdvicePrompt;
use App\Mcp\Prompts\FinancialHealthPrompt;
use App\Mcp\Resources\AccountBalancesResource;
use App\Mcp\Resources\FinancialOverviewResource;
use App\Mcp\Resources\RecentTransactionsResource;
use App\Mcp\Tools\Accounts\AdjustBalanceTool;
use App\Mcp\Tools\Accounts\CreateAccountTool;
use App\Mcp\Tools\Accounts\DeleteAccountTool;
use App\Mcp\Tools\Accounts\GetAccountTool;
use App\Mcp\Tools\Accounts\ListAccountsTool;
use App\Mcp\Tools\Accounts\UpdateAccountTool;
use App\Mcp\Tools\Budgets\CreateBudgetTool;
use App\Mcp\Tools\Budgets\DeleteBudgetTool;
use App\Mcp\Tools\Budgets\GetBudgetTool;
use App\Mcp\Tools\Budgets\ListBudgetsTool;
use App\Mcp\Tools\Budgets\UpdateBudgetTool;
use App\Mcp\Tools\Categories\CreateCategoryTool;
use App\Mcp\Tools\Categories\DeleteCategoryTool;
use App\Mcp\Tools\Categories\GetCategoryTool;
use App\Mcp\Tools\Categories\ListCategoriesTool;
use App\Mcp\Tools\Categories\UpdateCategoryTool;
use App\Mcp\Tools\Payees\CreatePayeeTool;
use App\Mcp\Tools\Payees\DeletePayeeTool;
use App\Mcp\Tools\Payees\GetPayeeTool;
use App\Mcp\Tools\Payees\ListPayeesTool;
use App\Mcp\Tools\Payees\UpdatePayeeTool;
use App\Mcp\Tools\Transactions\BulkReconcileTool;
use App\Mcp\Tools\Transactions\ConfirmUploadTool;
use App\Mcp\Tools\Transactions\CreateTransactionTool;
use App\Mcp\Tools\Transactions\DeleteAttachmentTool;
use App\Mcp\Tools\Transactions\DeleteTransactionTool;
use App\Mcp\Tools\Transactions\GetTransactionTool;
use App\Mcp\Tools\Transactions\ListAttachmentsTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Mcp\Tools\Transactions\ReconcileTransactionTool;
use App\Mcp\Tools\Transactions\RequestUploadUrlTool;
use App\Mcp\Tools\Transactions\UpdateTransactionTool;
use Laravel\Mcp\Server;

class BokkuServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Bokku Personal Finance';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * Return all tools in a single page (default is 15).
     */
    public int $defaultPaginationLength = 50;

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Bokku is a personal finance management system. This MCP server provides tools to:

        ## Accounts
        - List, get, create, update, and delete financial accounts
        - Adjust account balances for reconciliation

        ## Transactions
        - List, get, create, update, and delete transactions
        - Support for income, expense, and transfer types
        - Reconcile individual or bulk transactions
        - Upload, list, and delete receipt attachments (supports JPEG, PNG, GIF, WebP, PDF)

        ## Categories
        - List, get, create, update, and delete categories
        - Categories are typed as either 'income' or 'expense'

        ## Payees
        - List, get, create, update, and delete payees/merchants
        - Payees can have a default category for automatic categorization

        ## Budgets
        - List, get, create, update, and delete budgets
        - Budgets are linked to categories with weekly, monthly, or annual periods
        - Track spending progress against budget with spent, remaining, and percentage

        ## Important Notes
        - All monetary amounts are in decimal format (e.g., 100.50 for $100.50)
        - All data is scoped to the authenticated user
        - Transfers require both from_account_id and to_account_id
        - Regular transactions require account_id
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Account Tools
        ListAccountsTool::class,
        GetAccountTool::class,
        CreateAccountTool::class,
        UpdateAccountTool::class,
        DeleteAccountTool::class,
        AdjustBalanceTool::class,

        // Transaction Tools
        ListTransactionsTool::class,
        GetTransactionTool::class,
        CreateTransactionTool::class,
        UpdateTransactionTool::class,
        DeleteTransactionTool::class,
        ReconcileTransactionTool::class,
        BulkReconcileTool::class,

        // Attachment Tools
        ListAttachmentsTool::class,
        RequestUploadUrlTool::class,
        ConfirmUploadTool::class,
        DeleteAttachmentTool::class,

        // Category Tools
        ListCategoriesTool::class,
        GetCategoryTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,
        DeleteCategoryTool::class,

        // Payee Tools
        ListPayeesTool::class,
        GetPayeeTool::class,
        CreatePayeeTool::class,
        UpdatePayeeTool::class,
        DeletePayeeTool::class,

        // Budget Tools
        ListBudgetsTool::class,
        GetBudgetTool::class,
        CreateBudgetTool::class,
        UpdateBudgetTool::class,
        DeleteBudgetTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        FinancialOverviewResource::class,
        AccountBalancesResource::class,
        RecentTransactionsResource::class,
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        AnalyzeSpendingPrompt::class,
        BudgetAdvicePrompt::class,
        FinancialHealthPrompt::class,
    ];
}
