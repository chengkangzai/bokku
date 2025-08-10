# 📊 Bokku - Simple & Elegant Personal Finance Manager

## Philosophy
**Simple. Elegant. Powerful.** A personal finance manager that just works. No unnecessary complexity, just the features you need with a beautiful interface.

## Core Concept: Double-Entry Made Simple

Every transaction moves money from one place to another. That's it.
- **Income**: Money comes into your account
- **Expense**: Money goes out of your account  
- **Transfer**: Money moves between your accounts

The system automatically ensures everything balances, so you don't have to think about it.

## Technology Stack

- **Laravel 11** - Robust and reliable
- **Filament 3** - Beautiful admin interface out of the box
- **MySQL** - Simple, proven database
- **Laravel Prism** - AI for receipt scanning (Phase 2)

## MVP Features (Phase 1: Weeks 1-4)

### 1. Accounts
Simple account types that make sense:
- **Bank Accounts** (checking, savings)
- **Cash** (physical money)
- **Credit Cards** 
- **Loans** (mortgage, car, student)

Each account shows:
- Current balance
- Recent transactions
- Simple charts

### 2. Transactions
One-click transaction entry:
- Amount
- Description  
- Category (auto-suggested)
- Date
- Account from/to

Features:
- Quick entry shortcuts
- Recent transactions repeat
- Smart categorization
- Receipt photo attachment

### 3. Categories
Simple expense/income categories:
- Auto-create from transactions
- Icon selection
- Color coding
- Monthly summaries

### 4. Dashboard
Everything you need at a glance:
- Net worth
- This month's spending
- Account balances
- Recent transactions
- Simple charts

### 5. Reports
Three essential reports:
- **Monthly Overview** - Income vs Expenses
- **Category Breakdown** - Where your money goes
- **Account History** - Individual account details

## Phase 2: Smart Features (Weeks 5-6)

### 1. Budgets
Simple envelope budgeting:
- Set monthly amount per category
- Visual progress bars
- Alerts when near limit
- Auto-rollover option

### 2. Recurring Transactions
Set and forget:
- Monthly bills (rent, utilities, subscriptions)
- Weekly expenses (groceries)
- Annual payments (insurance)
- Auto-create transactions

### 3. Rules Engine
Automate the boring stuff:
- If description contains "Starbucks" → Category: Coffee
- If amount > $1000 → Tag as "Large Purchase"
- Auto-assign categories based on patterns

### 4. Import
Easy bank data import:
- CSV file upload
- Simple column mapping
- Duplicate detection
- One-click import

## Phase 3: AI Enhancement (Weeks 7-8)

### 1. Receipt Scanning
Point, shoot, done:
- Take photo of receipt
- AI extracts amount, date, vendor
- Auto-categorize
- Attach to transaction

### 2. Smart Insights
Helpful, not overwhelming:
- "You spent 20% more on dining this month"
- "Your electricity bill is higher than usual"
- "You're on track with your budget"

### 3. Natural Search
Find anything instantly:
- "Show me all coffee purchases"
- "What did I spend at Target last month?"
- "Total dining expenses this year"

## Database Schema (Simplified)

```sql
-- Core Tables Only
users
accounts (bank, cash, credit, loan)
transactions (simple double-entry)
categories
budgets
recurring_transactions
attachments (receipts)
rules (automation)
```

## Implementation Plan

### Week 1-2: Foundation
- Laravel + Filament setup
- User authentication
- Account management
- Basic transactions

### Week 3-4: Core Features  
- Categories
- Dashboard
- Simple reports
- Transaction search

### Week 5-6: Automation
- Budgets
- Recurring transactions
- Rules engine
- CSV import

### Week 7-8: AI & Polish
- Receipt scanning
- Smart insights
- Natural search
- Mobile optimization

## Filament Resources

Keep it simple with just 5 main resources:

```php
AccountResource.php      // Manage accounts
TransactionResource.php  // Enter/view transactions
CategoryResource.php     // Organize spending
BudgetResource.php      // Set spending limits
RecurringResource.php   // Manage subscriptions
```

## Key Design Principles

### 1. One-Click Actions
- Quick transaction entry
- Repeat recent transactions
- Apply saved rules
- Generate reports

### 2. Smart Defaults
- Auto-suggest categories
- Remember common payees
- Pre-fill recurring amounts
- Intelligent date selection

### 3. Visual Clarity
- Color-coded categories
- Progress bars for budgets
- Simple, clear charts
- Mobile-friendly design

### 4. No Feature Creep
What we're NOT building:
- Complex investment tracking
- Multi-currency (v1)
- Advanced tax features
- Complicated permissions
- Excessive report types

## User Experience Flow

```
Login → Dashboard (see everything)
      ↓
Add Transaction (2 clicks max)
      ↓
Auto-categorized & saved
      ↓
Updated dashboard & reports
```

## Success Metrics

- Transaction entry: < 10 seconds
- Page load: < 1 second
- Learning curve: < 5 minutes
- Daily active use: < 2 minutes

## Future Enhancements (v2)

Only if users actually need them:
- Bank API connections
- Multi-currency support
- Investment tracking
- Family sharing
- Advanced analytics

## Why Bokku Will Succeed

1. **It's Simple** - Your grandma could use it
2. **It's Fast** - Everything is instant
3. **It's Smart** - AI helps without getting in the way
4. **It's Beautiful** - Filament makes it gorgeous
5. **It Works** - Reliable double-entry accounting

## Development Approach

1. Start with the absolute minimum
2. Make it work perfectly
3. Add features only when requested
4. Keep the interface clean
5. Test with real users constantly

## Technical Simplifications

- No microservices (monolith is fine)
- No complex caching (MySQL is fast)
- No separate API (Filament handles it)
- No separate mobile app (PWA)
- No complex permissions (user owns their data)

---

**Remember**: The best personal finance app is the one people actually use. Keep it simple, make it elegant, ensure it works.