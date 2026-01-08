# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Bokku is a personal finance manager built with Laravel 12, Filament v4 admin panel, and MySQL. The application is a multi-tenant system where each user has isolated financial data (accounts, transactions, categories, budgets, payees).

## Core Development Commands

### Testing
```bash
php artisan test                                    # Run all tests
php artisan test --parallel                          # Run tests in parallel (faster)
php artisan test tests/Feature/Filament/Resources/AccountResourceTest.php  # Run specific file
php artisan test --filter=testName                   # Filter by test name
```

### Code Quality
```bash
./vendor/bin/pint                 # Format all code with Laravel Pint
./vendor/bin/pint --dirty         # Format only changed files
```

### Development Server
```bash
composer dev                      # Start all services (server, queue, logs, Vite)
```

### Database
```bash
php artisan migrate               # Run migrations
php artisan migrate:fresh --seed  # Fresh migration with seeders
```

## Architecture Overview

### Core Models & Relationships

The application uses **multi-tenant architecture** where all data is scoped by `user_id`:

- **User** → hasMany → Accounts, Categories, Transactions, Budgets, Payees, RecurringTransactions, TransactionRules
- **Account** → hasMany → Transactions (types: bank, cash, credit_card, loan)
- **Category** → hasMany → Transactions (types: income, expense)
- **Transaction** → belongsTo → Account, Category, User, Payee
  - Type enum: `income`, `expense`, `transfer`
  - Transfer transactions use `account_id` (from) and `to_account_id`
  - Regular transactions use `account_id`
- **Payee** → hasMany → Transactions (with optional default category auto-fill)
- **Budget** → belongsTo → Category (unique per user+category)
- **RecurringTransaction** → generates → Transactions automatically
- **TransactionRule** → auto-categorizes transactions based on conditions

### Money Handling

All monetary values are stored in **cents** (integer) in the database. The `MoneyCast` handles conversion:
- Database: `100` (cents) ↔ PHP/Display: `1.00` (dollars)
- Factory states like `withAmount(100.50)` accept dollar values

### Filament Resources Structure

Resources are organized by domain in `app/Filament/Resources/{Domain}/`:
```
app/Filament/Resources/
├── Accounts/AccountResource.php
├── Transactions/TransactionResource.php
├── Categories/CategoryResource.php
├── Budgets/BudgetResource.php
├── Payees/PayeeResource.php
├── RecurringTransactions/RecurringTransactionResource.php
└── TransactionRules/TransactionRuleResource.php
```

All resources implement **user data scoping** via `modifyQueryUsing()`:
```php
->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()))
```

### MCP Server Integration

The application exposes an MCP server for AI assistant integration in `app/Mcp/`:
- **Tools**: CRUD operations for accounts, transactions, categories, budgets, payees
- **Prompts**: Spending analysis, budget advice, financial health assessment
- **Resources**: Financial overview, account balances, recent transactions

### Testing Architecture

Tests mirror the source code structure:
- `tests/Feature/Filament/Resources/` → Filament resource tests
- `tests/Feature/Filament/Widgets/` → Dashboard widget tests
- `tests/Unit/Models/` → Model unit tests

Test patterns:
```php
// Authenticate before testing Filament
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Use livewire() for Filament component testing
livewire(ListAccounts::class)
    ->assertCanSeeTableRecords($userAccounts)
    ->assertCountTableRecords(3);

// Use factory states for specific transaction types
Transaction::factory()->income()->create();
Transaction::factory()->expense()->withAmount(50.00)->create();
Transaction::factory()->transfer()->create();
```

### Database Constraints

Key unique constraints to be aware of:
- **categories**: `[user_id, name, type]` - same category name allowed for different types
- **budgets**: `[user_id, category_id]` - one budget per category per user
- **payees**: `[user_id, name]` - unique payee names per user

### Key Technical Decisions

1. **Multi-tenancy**: All queries scoped by `user_id` at Filament resource level
2. **Balance tracking**: Account balances denormalized and recalculated on transaction changes via model events
3. **Transfer handling**: Single transaction record with source (`account_id`) and destination (`to_account_id`)
4. **Payee totals**: Automatically recalculated when transactions change
5. **Transaction rules**: Applied automatically on transaction creation (unless from recurring)
6. **Factory states**: `income()`, `expense()`, `transfer()`, `withAmount()`, `thisMonth()`, `lastMonth()`, `reconciled()`

## Environment Configuration

- **Database**: MySQL (bokku)
- **Testing**: SQLite in-memory
- **Sessions/Cache/Queues**: Database drivers
