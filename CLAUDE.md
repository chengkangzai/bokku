# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Bokku is a personal finance manager built with Laravel 12, Filament 3.0 admin panel, and uses MySQL as the database. The application is a multi-tenant system where each user has isolated financial data (accounts, transactions, categories).

## Core Development Commands

### Testing
```bash
# Run all tests
php artisan test

# Run tests in parallel (faster)
php artisan test --parallel

# Run specific test file
php artisan test tests/Feature/Filament/Resources/AccountResourceTest.php

# Run tests with coverage
php artisan test --coverage
```

### Code Quality
```bash
# Format code with Laravel Pint
./vendor/bin/pint --parallel

# Run with specific file
./vendor/bin/pint app/Models/Account.php
```

### Development Server
```bash
# Start all development services (Laravel server, queue, logs, Vite)
composer dev

# Individual services
php artisan serve              # Laravel development server
npm run dev                     # Vite development server
php artisan queue:listen       # Queue worker
php artisan pail               # Real-time log viewer
```

### Database
```bash
# Run migrations
php artisan migrate

# Fresh migration with seeders
php artisan migrate:fresh --seed

# Rollback migrations
php artisan migrate:rollback
```

### Build Assets
```bash
npm run build                   # Production build
npm run dev                     # Development with hot reload
```

## Architecture Overview

### Core Models & Relationships

The application uses a **multi-tenant architecture** where all data is scoped by `user_id`:

- **User** → hasMany → **Accounts**, **Categories**, **Transactions**
- **Account** → hasMany → **Transactions** (including transfers)
- **Category** → hasMany → **Transactions**
- **Transaction** → belongsTo → **Account**, **Category**, **User**
  - Transfer transactions use `from_account_id` and `to_account_id`

### Filament Resources Structure

Each Filament resource follows this pattern:
```
app/Filament/Resources/
├── {Model}Resource.php           # Main resource configuration
└── {Model}Resource/Pages/
    ├── List{Models}.php          # Index page with table
    ├── Create{Model}.php         # Create form page
    └── Edit{Model}.php           # Edit form page
```

All resources implement:
- **User data scoping** via `modifyQueryUsing()` to ensure users only see their own data
- **Form schemas** with validation rules
- **Table configurations** with searchable, sortable columns
- **Bulk actions** and individual row actions

### Testing Architecture

Tests follow a **file-based organization** that mirrors the source code:
- `tests/Feature/Filament/Resources/` → Tests for Filament resources
- `tests/Feature/Filament/Widgets/` → Tests for dashboard widgets
- `tests/Unit/Models/` → Model unit tests

Test patterns use:
- **Pest PHP** testing framework with Laravel and Livewire plugins
- **RefreshDatabase** trait for test isolation
- **Database factories** for generating test data
- **Filament testing helpers**: `fillForm()`, `assertCanSeeTableRecords()`, `callTableAction()`

### Database Schema

Key tables with important constraints:
- **categories**: Unique constraint on `[user_id, name, type]`
- **accounts**: Tracks balance separately from transactions
- **transactions**: 
  - Type enum: `['income', 'expense', 'transfer']`
  - Transfer transactions require both `from_account_id` and `to_account_id`
  - Regular transactions require `account_id`

### Key Technical Decisions

1. **Multi-tenancy**: All queries are automatically scoped by `user_id` at the Filament resource level
2. **Balance tracking**: Account balances are denormalized for performance
3. **Transfer handling**: Transfers are single transactions with both source and destination accounts
4. **Testing strategy**: Comprehensive Filament component testing following official documentation patterns
5. **Factory states**: Specialized factory states for different transaction types (income/expense/transfer)

## Environment Configuration

Default database configuration (MySQL):
- Database: `bokku`
- Connection: `mysql`
- Testing uses SQLite in-memory database

Sessions, cache, and queues all use database drivers by default.
