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
- **Prism PHP** - Unified AI interface for multiple LLM providers
- **Ollama + Gemma 3 4B** - Free local AI for development
- **OpenAI GPT-4** - Production AI for advanced processing

### Prism PHP Setup

```bash
# Installation
composer require echolabsdev/prism

# Publish config
php artisan vendor:publish --tag=prism-config

# For local development - Ollama (Already Installed)
# Start Ollama server if not running
ollama serve

# Pull the model if not already available
ollama pull gemma3:4b  # Download Gemma 3 4B model

# Production - Use OpenAI
# Environment variables in .env
APP_ENV=local  # or production

# Local development with Ollama
OLLAMA_URL=http://localhost:11434/v1
AI_PROVIDER=ollama
AI_MODEL=gemma3:4b

# Production with OpenAI
OPENAI_API_KEY=your-api-key-here
OPENAI_ORGANIZATION=your-org-id  # optional
AI_PROVIDER=openai
AI_MODEL=gpt-4o-mini  # or gpt-3.5-turbo for cost efficiency
```

```php
// config/prism.php
return [
    'providers' => [
        // Local development with Ollama
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
        ],
        
        // Production with OpenAI
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION', null),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],
    ],
    
    'prism' => [
        'max_attempts' => 3,
        'retry_delay' => 1000,
        'default_provider' => env('AI_PROVIDER', 'ollama'),
        'default_model' => env('AI_MODEL', 'gemma3:4b'),
    ],
];
```

### Ollama Model Options

```bash
# Primary model for local development
ollama pull gemma3:4b      # 2.6GB - Good balance of speed and quality

# Alternative models if needed
ollama pull phi3:mini      # 2.3GB - Better reasoning
ollama pull llama3.2:3b    # 2.0GB - Faster alternative
ollama pull mistral:7b     # 4.1GB - Best quality (if you have RAM)

# Check available models
ollama list

# Test model locally
ollama run gemma3:4b "Extract date and amount from: Payment RM50.00 on 15/12/2024"
```

### AI Provider Abstraction

```php
// app/Services/AI/AIProviderService.php
namespace App\Services\AI;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Smalot\PdfParser\Parser as PdfParser;

class AIProviderService
{
    protected string $provider;
    protected string $model;
    
    public function __construct()
    {
        $this->provider = config('prism.prism.default_provider');
        $this->model = config('prism.prism.default_model');
    }
    
    /**
     * Get the appropriate provider enum based on environment
     */
    protected function getProvider(): Provider
    {
        return match($this->provider) {
            'ollama' => Provider::Ollama,
            'openai' => Provider::OpenAI,
            default => Provider::Ollama,
        };
    }
    
    /**
     * Process bank statement using appropriate AI provider
     */
    public function processStatement($file): array
    {
        $provider = $this->getProvider();
        
        // Adjust prompt based on provider capabilities
        $systemPrompt = $this->getSystemPrompt($provider);
        
        if ($provider === Provider::Ollama) {
            // Ollama with Gemma - text extraction only
            return $this->processWithOllama($file, $systemPrompt);
        } else {
            // OpenAI with GPT-4 Vision
            return $this->processWithOpenAI($file, $systemPrompt);
        }
    }
    
    private function processWithOllama($file, $systemPrompt): array
    {
        // For Ollama, extract text first using OCR/PDF parser
        $text = $this->extractTextFromFile($file);
        
        $response = Prism::text()
            ->using(Provider::Ollama, $this->model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt("Extract transactions from this text:\n\n" . $text)
            ->withClientOptions(['timeout' => 60])  // Longer timeout for local
            ->withProviderOptions([
                'temperature' => 0.1,  // Low temperature for consistency
                'num_ctx' => 4096,     // Context window
            ])
            ->generate();
            
        return json_decode($response->text, true);
    }
    
    private function processWithOpenAI($file, $systemPrompt): array
    {
        // OpenAI can process images directly with Vision API
        $response = Prism::text()
            ->using(Provider::OpenAI, $this->model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages([
                new UserMessage(
                    'Extract all transactions from this bank statement',
                    [Image::fromPath($file)]
                )
            ])
            ->withProviderOptions([
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ])
            ->generate();
            
        return json_decode($response->text, true);
    }
    
    /**
     * Extract text from PDF or image files
     */
    private function extractTextFromFile($file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if ($extension === 'pdf') {
            // Use PDF parser for PDF files
            $parser = new PdfParser();
            $pdf = $parser->parseFile($file);
            return $pdf->getText();
        }
        
        // Use Tesseract OCR for images
        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
            return (new TesseractOCR($file))
                ->lang('eng', 'msa')  // English + Malay
                ->run();
        }
        
        // For CSV files, just read directly
        if ($extension === 'csv') {
            return file_get_contents($file);
        }
        
        throw new \Exception("Unsupported file type: {$extension}");
    }
    
    private function getSystemPrompt($provider): string
    {
        if ($provider === Provider::Ollama) {
            // Simpler, more direct prompt for smaller models
            return <<<PROMPT
Extract transaction data from bank statement.
Return JSON array with these fields only:
- date (YYYY-MM-DD)
- description (transaction description)
- amount (number without currency)
- type (debit or credit)

Example output:
[{"date":"2024-01-15","description":"GRAB RIDE","amount":25.50,"type":"debit"}]
PROMPT;
        }
        
        // More sophisticated prompt for GPT-4
        return <<<PROMPT
You are a financial data extraction specialist for Malaysian banks.
Extract transaction data from this bank statement with these rules:

1. Dates: Convert to YYYY-MM-DD format
2. Amounts: Remove RM prefix, keep as decimal (1234.56)
3. Type: Determine if debit or credit
4. Description: Combine multi-line descriptions
5. Merchant: Extract merchant name from description

Return as JSON array with fields:
- date (YYYY-MM-DD)
- description (full text)
- merchant (extracted name)
- amount (decimal)
- type (debit/credit)
- balance (if available)

Handle Malaysian formats:
- RM for currency
- DD/MM/YYYY dates
- Comma thousand separators
PROMPT;
    }
}
```

### OCR Dependencies

```bash
# Install Tesseract OCR (for local text extraction)
# macOS
brew install tesseract
brew install tesseract-lang  # Additional language packs

# Ubuntu/Debian
sudo apt-get install tesseract-ocr
sudo apt-get install tesseract-ocr-msa  # Malay language pack

# PHP packages for OCR and PDF parsing
composer require thiagoalessio/tesseract_ocr
composer require smalot/pdfparser
```

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

### 3. Rules Engine ✅
Automate the boring stuff:
- If description contains "Starbucks" → Category: Coffee
- If amount > $1000 → Tag as "Large Purchase"
- Auto-assign categories based on patterns

## Phase 3: AI Enhancement (Weeks 7-8)

### 1. Smart Import with AI (Powered by Prism PHP + OpenAI)

#### Core Technology
- **Prism PHP**: Unified AI interface for multiple providers
- **OpenAI GPT-4**: For intelligent text extraction and categorization
- **Laravel Queue**: For processing large imports in background

#### Bank Statement Import
**Supported Formats:**
- **PDF Bank Statements** (All Malaysian banks)
  - Maybank: PDF statements with table extraction
  - Hong Leong Bank: CSV export support
  - CIMB: PDF with structured data
  - Public Bank: PDF statements
  
**AI Processing Pipeline:**
1. **Upload**: User uploads PDF/CSV file
2. **Text Extraction**: 
   - PDF: Use Prism to extract text/tables via OpenAI Vision API
   - CSV: Direct parsing with column detection
3. **Structure Detection**: AI identifies:
   - Date columns/format
   - Description fields
   - Debit/Credit amounts
   - Balance columns
   - Account numbers
4. **Smart Parsing**: 
   - Auto-detect date formats (DD/MM/YYYY, MM-DD-YY, etc.)
   - Handle multi-line descriptions
   - Identify transfer vs payment transactions
   - Extract merchant names from descriptions
5. **Categorization**: AI suggests categories based on:
   - Merchant name patterns
   - Transaction descriptions
   - Amount ranges
   - Historical user patterns
6. **Duplicate Detection**: 
   - Fuzzy matching on date + amount + description
   - Skip already imported transactions
   - Merge partial matches

#### E-Wallet Import
**Touch n Go eWallet:**
- **Method 1**: Screenshot OCR
  - User takes screenshots of transaction history
  - AI extracts text using Vision API
  - Parses Malaysian format dates and amounts
- **Method 2**: Email Receipts
  - Forward TnG receipts to import@bokku.app
  - AI extracts transaction details from email

**GrabPay/Boost/ShopeePay:**
- Similar screenshot OCR approach
- AI trained on Malaysian e-wallet formats
- Automatic merchant categorization

#### Implementation Details

```php
// PrismImportService.php
use EllisIO\Prism\Facades\Prism;
use EllisIO\Prism\Enums\Provider;

class PrismImportService
{
    public function processStatement($file)
    {
        // 1. Extract text/structure from file
        $response = Prism::provider(Provider::OpenAI)
            ->model('gpt-4-vision-preview')
            ->withMessages([
                'role' => 'system',
                'content' => 'Extract transaction data from this bank statement. Return as JSON with fields: date, description, amount, type (debit/credit), balance'
            ])
            ->withImage($file)
            ->generate();
            
        // 2. Parse extracted data
        $transactions = json_decode($response->text, true);
        
        // 3. Smart categorization
        foreach ($transactions as &$transaction) {
            $category = $this->suggestCategory($transaction);
            $transaction['suggested_category'] = $category;
        }
        
        return $transactions;
    }
    
    private function suggestCategory($transaction)
    {
        return Prism::provider(Provider::OpenAI)
            ->model('gpt-3.5-turbo')
            ->withMessages([
                'role' => 'system',
                'content' => 'Categorize this Malaysian transaction. Categories: Food, Transport, Shopping, Bills, Entertainment, Healthcare, Education, Others'
            ], [
                'role' => 'user', 
                'content' => $transaction['description']
            ])
            ->generate()->text;
    }
}
```

#### Import Workflow UI

```php
// ImportWizard.php (Filament)
1. Upload Step
   - Drag & drop file upload
   - Support PDF, CSV, images
   - Multiple file selection

2. Preview Step  
   - Show extracted transactions
   - AI-suggested categories (editable)
   - Duplicate warnings
   - Column mapping for CSV

3. Review Step
   - Final transaction list
   - Bulk category assignment
   - Select account to import to
   - Date range selection

4. Import Step
   - Progress bar
   - Skip/merge duplicates
   - Error handling
   - Import summary
```

### 2. Receipt Scanning 2.0
Enhanced receipt processing:
- **Multi-receipt batch upload**: Process multiple receipts at once
- **Malaysian format support**: RM currency, GST/SST detection
- **Split bill detection**: Identify shared expenses
- **Loyalty points extraction**: Track points from receipts
- **E-receipt QR codes**: Scan QR for digital receipts

### 3. Smart Insights
AI-powered financial advice:
- "You spent 20% more on dining this month"
- "Your TNB bill is higher than usual - RM230 vs RM180 average"
- "You're on track with your budget"
- "Unusual transaction detected: RM2000 at 2am"
- "Consider reviewing your Astro subscription - unused for 2 months"

### 4. Natural Language Processing
Conversational transaction search:
- "Show me all mamak purchases"
- "What did I spend at Pavilion last month?"
- "Total Grab rides this year"
- "How much did I spend on petrol in December?"
- "Compare this month's groceries with last month"

### 5. AI Import Architecture

#### Database Schema for Imports

```sql
-- Import tracking tables
import_sessions (
    id, user_id, file_name, file_type, 
    status, total_rows, processed_rows,
    imported_count, skipped_count, 
    error_count, created_at
)

import_mappings (
    id, user_id, bank_name,
    date_column, description_column,
    debit_column, credit_column,
    balance_column, saved_mapping
)

import_transactions (
    id, import_session_id, 
    original_data (json), 
    parsed_data (json),
    ai_suggestions (json),
    status, transaction_id
)
```

#### Service Architecture

```php
// app/Services/AI/PrismImportService.php
namespace App\Services\AI;

use EllisIO\Prism\Prism;
use App\Models\ImportSession;
use Illuminate\Support\Facades\Queue;

class PrismImportService
{
    private Prism $prism;
    
    public function processFile($file, $userId)
    {
        $session = ImportSession::create([
            'user_id' => $userId,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->extension(),
            'status' => 'processing'
        ]);
        
        // Queue for background processing
        Queue::push(new ProcessImportJob($session, $file));
        
        return $session;
    }
}

// app/Jobs/ProcessImportJob.php
class ProcessImportJob implements ShouldQueue
{
    public function handle()
    {
        match($this->session->file_type) {
            'pdf' => $this->processPDF(),
            'csv' => $this->processCSV(),
            'png', 'jpg' => $this->processImage(),
        };
    }
    
    private function processPDF()
    {
        // Extract tables from PDF using AI
        $extraction = $this->prism
            ->provider(Provider::OpenAI)
            ->model('gpt-4-vision-preview')
            ->withSystemPrompt($this->getPDFExtractionPrompt())
            ->withImage($this->file)
            ->generate();
            
        $this->parseAndStore($extraction);
    }
}
```

#### Filament Import Resource

```php
// app/Filament/Resources/ImportResource.php
class ImportResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                Step::make('Upload')
                    ->schema([
                        FileUpload::make('file')
                            ->acceptedFileTypes(['application/pdf', 'text/csv', 'image/*'])
                            ->maxSize(10240)
                            ->required(),
                        Select::make('bank')
                            ->options([
                                'maybank' => 'Maybank',
                                'hongleong' => 'Hong Leong Bank',
                                'cimb' => 'CIMB Bank',
                                'publicbank' => 'Public Bank',
                                'tng' => 'Touch n Go eWallet',
                                'grab' => 'GrabPay',
                                'custom' => 'Other/Custom Format'
                            ])
                    ]),
                Step::make('Preview')
                    ->schema([
                        // Dynamic preview based on extracted data
                    ]),
                Step::make('Map & Import')
                    ->schema([
                        // Column mapping and import options
                    ])
            ])
        ]);
    }
}
```

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

-- AI Import Tables
import_sessions
import_mappings
import_transactions
ai_categorization_history
```

### 6. Malaysian Bank-Specific Import Formats

#### Maybank
```php
// Maybank PDF Pattern Recognition
[
    'date_format' => 'DD/MM/YYYY',
    'date_pattern' => '/^\d{2}\/\d{2}\/\d{4}/',
    'description_lines' => 2, // Multi-line descriptions
    'amount_format' => '1,234.56',
    'keywords' => ['MAYBANK', 'MALAYAN BANKING'],
    'sample_patterns' => [
        'ATM CDM' => 'ATM/Cash',
        'POS PURCHASE' => 'Shopping',
        'IBG TRANSFER' => 'Transfer',
        'GIRO' => 'Bills'
    ]
]
```

#### Hong Leong Bank
```php
// HLB CSV Export Format
[
    'delimiter' => ',',
    'columns' => [
        'Transaction Date',
        'Value Date', 
        'Description',
        'Debit',
        'Credit',
        'Balance'
    ],
    'date_format' => 'DD-MM-YYYY',
    'encoding' => 'UTF-8'
]
```

#### Touch n Go eWallet
```php
// TnG Screenshot OCR Patterns
[
    'header_pattern' => 'Transaction History',
    'date_format' => 'DD MMM YYYY, HH:MM AM/PM',
    'merchant_extraction' => '/^([^-]+)/', // Before first dash
    'amount_pattern' => '/RM\s?([\d,]+\.?\d*)/',
    'status_indicators' => ['Successful', 'Failed', 'Pending']
]
```

### 7. AI Training Prompts

#### Bank Statement Extraction Prompt
```
You are a financial data extraction specialist for Malaysian banks.
Extract transaction data from this bank statement with these rules:

1. Dates: Convert to YYYY-MM-DD format
2. Amounts: Remove RM prefix, keep as decimal (1234.56)
3. Type: Determine if debit or credit
4. Description: Combine multi-line descriptions
5. Merchant: Extract merchant name from description

Return as JSON array with fields:
- date (YYYY-MM-DD)
- description (full text)
- merchant (extracted name)
- amount (decimal)
- type (debit/credit)
- balance (if available)

Handle Malaysian formats:
- RM for currency
- DD/MM/YYYY dates
- Comma thousand separators
```

#### Category Suggestion Prompt
```
Categorize this Malaysian transaction into one of these categories:

Categories:
- Food & Dining (restaurants, cafes, food delivery)
- Groceries (supermarkets, wet markets, mini marts)
- Transport (Grab, petrol, parking, tolls, LRT/MRT)
- Shopping (clothes, electronics, online shopping)
- Bills & Utilities (TNB, water, internet, phone)
- Entertainment (movies, games, subscriptions)
- Healthcare (clinics, pharmacy, insurance)
- Education (courses, books, tuition)
- Home (rent, maintenance, furniture)
- Personal Care (salon, gym, spa)
- Others

Transaction: {description}
Amount: RM {amount}

Consider Malaysian context (mamak, pasar, TNB, Astro, etc.)
Return only the category name.
```

## Implementation Plan

### Week 1-2: Foundation ✅
- Laravel + Filament setup
- User authentication
- Account management
- Basic transactions

### Week 3-4: Core Features ✅ 
- Categories
- Dashboard
- Simple reports
- Transaction search

### Week 5-6: Automation ✅
- Budgets
- Recurring transactions
- Rules engine
- ~~CSV import~~ (Moved to AI phase)

### Week 7-8: AI & Polish (Current Phase)

#### Week 7: AI Infrastructure
**Day 1-2: Prism PHP Setup**
- Install and configure Prism PHP
- Set up OpenAI API credentials
- Create AI service layer
- Implement queue jobs for processing

**Day 3-4: Basic Import**
- CSV parser with AI column detection
- Simple PDF text extraction
- Import session tracking
- Duplicate detection logic

**Day 5: Bank-Specific Parsers**
- Maybank PDF parser
- Hong Leong CSV handler
- CIMB statement processor

#### Week 8: Advanced AI Features
**Day 1-2: E-Wallet Integration**
- Touch n Go screenshot OCR
- GrabPay transaction extraction
- Boost/ShopeePay support

**Day 3-4: Smart Categorization**
- Train categorization model
- Historical pattern learning
- Bulk categorization UI
- Category confidence scores

**Day 5: Polish & Testing**
- Import wizard UI
- Error handling
- Performance optimization
- User documentation

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