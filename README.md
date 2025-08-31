# Bokku 📚💰

**Personal Finance Management Made Simple**

Bokku is a self-hosted personal finance manager built with Laravel and Filament, inspired by [Firefly III](https://firefly-iii.org/). The name comes from an evolution: **book keeping** → **buku** (books in Malay) → **bokku** (making it personal) - representing your personal book keeping software.

## ✨ Features

### 📊 Financial Management
- **Multi-tenant architecture** - Secure user data isolation
- **Account management** - Bank accounts, cash, credit cards, and loans
- **Transaction tracking** - Income, expenses, and transfers
- **Category organization** - Customizable transaction categories
- **Balance tracking** - Real-time account balance updates

### 🏦 Malaysian Banking Focus
- **Multi-currency support** with MYR as default
- **Local bank integration** patterns (Maybank, CIMB, Public Bank, etc.)
- **Malaysian date formats** and transaction patterns
- **Local payment method recognition** (DuitNow, FPX, etc.)

### 🎯 Advanced Features
- **Budget management** - Set and track spending limits
- **Recurring transactions** - Automate regular income/expenses  
- **Transaction rules** - Auto-categorize based on patterns
- **AI-powered import** - Smart transaction extraction from bank statements
- **Media attachments** - Attach receipts and documents
- **Comprehensive reporting** - Track your financial health

### 🎨 Modern UI/UX
- **Filament admin panel** - Beautiful, responsive interface
- **Real-time notifications** - Stay updated on your finances
- **Dashboard widgets** - Quick overview of your financial status
- **Mobile-friendly** - Access your finances anywhere

## 🛠 Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Admin Panel**: Filament v4
- **Frontend**: Livewire v3 + Alpine.js
- **Styling**: Tailwind CSS v4
- **Database**: MySQL
- **Testing**: Pest PHP v3
- **AI Integration**: Multi-provider AI service for document processing
- **File Processing**: Spatie Media Library

## 🚀 Getting Started

### Prerequisites

- PHP 8.4+
- Composer
- Node.js & NPM
- MySQL 8.0+
- Laravel Herd (recommended) or your preferred local development environment

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/chengkangzai/bokku.git
   cd bokku
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your database** in `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=bokku
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations and seed data**
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Build frontend assets**
   ```bash
   npm run build
   # or for development
   npm run dev
   ```

7. **Start the application**
   ```bash
   php artisan serve
   # or with Laravel Herd: bokku.test
   ```

8. **Access the application**
   - Default URL: `http://localhost:8000`
   - Login with the seeded user account (check DatabaseSeeder for credentials)

## 🏗 Development

### Useful Commands

```bash
# Start development server with all services
composer dev

# Run tests
php artisan test
php artisan test --parallel

# Code formatting
./vendor/bin/pint

# Clear caches
php artisan optimize:clear
```

### Project Structure

```
app/
├── Filament/           # Admin panel resources, widgets, pages
├── Models/            # Eloquent models
├── Services/          # Business logic services
│   ├── AI/           # AI provider integration
│   └── Import/       # Transaction import handlers
├── Http/             # Controllers and middleware
└── Providers/        # Service providers

resources/
└── views/            # Blade templates

tests/
├── Feature/          # Integration tests
└── Unit/            # Unit tests
```

## 🎯 Key Features Breakdown

### Multi-Tenant Architecture
Every piece of financial data is automatically scoped to the authenticated user, ensuring complete data isolation and privacy.

### Smart Transaction Import
The AI-powered import system can process various file formats and automatically:
- Extract transaction data
- Detect bank names
- Categorize transactions based on your existing categories
- Handle Malaysian banking formats and patterns

### Flexible Account Types
- **Bank Accounts**: Traditional checking/savings accounts
- **Cash**: Physical money tracking
- **Credit Cards**: Outstanding balance management
- **Loans**: Debt tracking with liability handling

### Budget Management
Set monthly/yearly budgets for categories and track spending against your limits with visual indicators and notifications.

## 🤝 Inspiration & Credits

Bokku is inspired by [Firefly III](https://firefly-iii.org/), an excellent open-source personal finance manager. While Firefly III provides a comprehensive foundation, Bokku focuses on:

- **Malaysian market specifics** - Local banking patterns, currencies, and payment methods
- **Modern Laravel stack** - Latest Laravel features with Filament for rapid development
- **AI-enhanced import** - Smart document processing for easier data entry
- **Simplified UX** - Streamlined interface focused on essential features

## 📜 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Acknowledgments

- [Firefly III](https://firefly-iii.org/) - The inspiration and reference for personal finance management
- [Laravel](https://laravel.com/) - The PHP framework that powers Bokku
- [Filament](https://filamentphp.com/) - The admin panel that makes development a joy
- [Spatie](https://spatie.be/) - For the excellent Laravel packages used throughout

---

**Built with ❤️ in Malaysia** 🇲🇾