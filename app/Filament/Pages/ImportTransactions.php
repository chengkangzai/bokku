<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Import\UnifiedImportHandler;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportTransactions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Import Transactions';

    protected static ?string $title = 'Import Transactions';

    protected static ?string $slug = 'import-transactions';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.import-transactions-wizard';

    // Wizard form state
    public ?array $data = [];

    // Processing results
    public array $extractedData = [];

    public array $importResults = [];

    public bool $isProcessing = false;

    protected UnifiedImportHandler $importHandler;

    public function boot(UnifiedImportHandler $importHandler): void
    {
        $this->importHandler = $importHandler;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Step::make('Upload & Configure')
                        ->description('Upload your bank statement and select import settings')
                        ->schema([
                            FileUpload::make('file')
                                ->label('Bank Statement File')
                                ->acceptedFileTypes(['application/pdf', 'text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(10240) // 10MB
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpanFull(),

                            Select::make('account_id')
                                ->label('Import to Account')
                                ->options(fn () => Account::where('user_id', Auth::id())
                                    ->where('is_active', true)
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->columnSpanFull(),

                            Textarea::make('ai_instructions')
                                ->label('Additional Instructions for AI (Optional)')
                                ->placeholder('e.g., "This is a credit card statement" or "Ignore transactions before March 2024"')
                                ->rows(3)
                                ->columnSpanFull(),

                            Actions::make([
                                Action::make('processFile')
                                    ->label('Process File')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('primary')
                                    ->disabled(fn ($get) => empty($get('file')) || empty($get('account_id')))
                                    ->action(function () {
                                        $this->processFileWithConfiguration();
                                    })
                                    ->visible(fn ($get) => ! empty($get('file')) && ! empty($get('account_id')) && empty($this->extractedData)),
                            ])->columnSpanFull(),

                            Placeholder::make('processing_status')
                                ->content(function () {
                                    if (! empty($this->extractedData)) {
                                        $count = count($this->extractedData['transactions'] ?? []);

                                        return new HtmlString("<div class='text-sm text-green-600'>✓ File processed successfully. Found {$count} transactions. Click 'Next' to review.</div>");
                                    }

                                    return '';
                                })
                                ->columnSpanFull(),

                            Hidden::make('extracted_data'),
                        ])
                        ->columns(2),

                    Step::make('Review Transactions')
                        ->description('Review and edit the extracted transactions')
                        ->schema([
                            Placeholder::make('import_summary')
                                ->content(fn () => new HtmlString($this->getImportSummary()))
                                ->columnSpanFull(),

                            TableRepeater::make('transactions')
                                ->label('Extracted Transactions')
                                ->headers([
                                    Header::make('date')
                                        ->width('120px')
                                        ->markAsRequired(),
                                    Header::make('description')
                                        ->width('300px')
                                        ->markAsRequired(),
                                    Header::make('amount')
                                        ->width('120px')
                                        ->markAsRequired(),
                                    Header::make('type')
                                        ->width('120px')
                                        ->markAsRequired(),
                                    Header::make('category')
                                        ->width('150px'),
                                ])
                                ->schema([
                                    DatePicker::make('date')
                                        ->required()
                                        ->displayFormat('d/m/Y'),
                                    TextInput::make('description')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('amount')
                                        ->required()
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('RM'),
                                    Select::make('type')
                                        ->required()
                                        ->options([
                                            'income' => 'Income',
                                            'expense' => 'Expense',
                                            'transfer' => 'Transfer',
                                        ]),
                                    TextInput::make('category')
                                        ->placeholder('Auto-categorized'),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add Transaction')
                                ->columnSpanFull()
                                ->reorderable(false)
                                ->collapsible(),
                        ])
                        ->columns(1),

                    Step::make('Import Results')
                        ->description('Review the import results and finish')
                        ->schema([
                            Placeholder::make('results')
                                ->content(fn () => new HtmlString($this->getResultsSummary()))
                                ->columnSpanFull(),
                        ])
                        ->columns(1),
                ])
                    ->columnSpanFull()
                    ->submitAction(new HtmlString('<button type="submit" class="filament-button filament-button-size-sm inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-3 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">Import Transactions</button>'))
                    ->skippable()
                    ->persistStepInQueryString(),
            ])
            ->statePath('data');
    }

    /**
     * Process file with all configuration
     */
    public function processFileWithConfiguration(): void
    {
        // Get form data
        $fileData = $this->data['file'] ?? null;
        $accountId = $this->data['account_id'] ?? null;
        $userInstructions = $this->data['ai_instructions'] ?? null;

        // Validate required fields
        if (empty($fileData) || empty($accountId)) {
            Notification::make()
                ->title('Missing required fields')
                ->body('Please upload a file and select an account before processing')
                ->warning()
                ->send();

            return;
        }

        $this->isProcessing = true;

        try {
            // Get the uploaded file
            $filePath = is_array($fileData) ? reset($fileData) : $fileData;

            // Get the actual temporary file from storage
            $uploadedFile = null;
            if (is_string($filePath)) {
                // Get file from Livewire temporary storage
                $uploadedFile = TemporaryUploadedFile::createFromLivewire($filePath);
            } elseif ($filePath instanceof TemporaryUploadedFile) {
                $uploadedFile = $filePath;
            }

            if (! $uploadedFile) {
                throw new \Exception('Unable to process the uploaded file');
            }

            // Get file content
            $content = file_get_contents($uploadedFile->getRealPath());
            $fileType = $uploadedFile->getClientOriginalExtension();

            // Process with AI
            $result = $this->importHandler->processFile(
                $content,
                $fileType,
                $userInstructions
            );

            // Store extracted data
            $this->extractedData = $result;
            $this->data['extracted_data'] = $result;
            $this->data['transactions'] = $result['transactions'];

            Notification::make()
                ->title('File processed successfully')
                ->body("Found {$result['metadata']['total_transactions']} transactions from {$result['bank_name']}")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Processing failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Import transactions when wizard is submitted
     */
    public function submit(): void
    {
        $formData = $this->form->getState();

        if (empty($formData['transactions'])) {
            Notification::make()
                ->title('No transactions to import')
                ->body('Please ensure there are transactions to import')
                ->warning()
                ->send();

            return;
        }

        if (empty($formData['account_id'])) {
            Notification::make()
                ->title('No account selected')
                ->body('Please select an account to import transactions into')
                ->warning()
                ->send();

            return;
        }

        $this->isProcessing = true;

        try {
            $imported = 0;
            $errors = [];

            foreach ($formData['transactions'] as $index => $transactionData) {
                try {
                    // Create category if provided
                    $categoryId = null;
                    if (! empty($transactionData['category'])) {
                        $category = Category::firstOrCreate(
                            [
                                'user_id' => Auth::id(),
                                'name' => $transactionData['category'],
                                'type' => $transactionData['type'] === 'income' ? 'income' : 'expense',
                            ],
                            ['color' => '#6B7280']
                        );
                        $categoryId = $category->id;
                    }

                    // Create transaction
                    Transaction::create([
                        'user_id' => Auth::id(),
                        'account_id' => $formData['account_id'],
                        'category_id' => $categoryId,
                        'type' => $transactionData['type'],
                        'amount' => $transactionData['amount'],
                        'description' => $transactionData['description'],
                        'date' => $transactionData['date'],
                        'reference' => $transactionData['reference'] ?? null,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = 'Row '.($index + 1).": {$e->getMessage()}";
                }
            }

            $this->importResults = [
                'imported' => $imported,
                'errors' => $errors,
                'total' => count($formData['transactions']),
            ];

            if ($imported > 0) {
                Notification::make()
                    ->title('Import completed')
                    ->body("{$imported} transactions imported successfully")
                    ->success()
                    ->send();
            }

            if (! empty($errors)) {
                Notification::make()
                    ->title('Import completed with errors')
                    ->body(count($errors).' transactions failed to import')
                    ->warning()
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Reset the import process
     */
    public function resetImport(): void
    {
        $this->data = [];
        $this->extractedData = [];
        $this->importResults = [];
        $this->form->fill();

        Notification::make()
            ->title('Import reset')
            ->body('You can now start a new import process')
            ->success()
            ->send();
    }

    /**
     * Get import summary for step 2
     */
    protected function getImportSummary(): string
    {
        if (empty($this->extractedData)) {
            return '<div class="text-sm text-gray-600">No file processed yet. Please go back and upload a file.</div>';
        }

        $metadata = $this->extractedData['metadata'] ?? [];
        $totalTransactions = $metadata['total_transactions'] ?? 0;
        $dateRange = $metadata['date_range'] ?? 'Unknown';

        return '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4">'.
            '<h3 class="text-sm font-medium text-blue-800">Import Summary</h3>'.
            '<dl class="mt-2 text-sm text-blue-700">'.
            '<dt class="inline font-medium">Transactions Found:</dt> <dd class="inline ml-1">'.$totalTransactions.'</dd><br>'.
            '<dt class="inline font-medium">Date Range:</dt> <dd class="inline ml-1">'.$dateRange.'</dd>'.
            '</dl></div>';
    }

    /**
     * Get results summary for step 3
     */
    protected function getResultsSummary(): string
    {
        if (empty($this->importResults)) {
            return '<div class="text-sm text-gray-600">Import not completed yet.</div>';
        }

        $imported = $this->importResults['imported'] ?? 0;
        $total = $this->importResults['total'] ?? 0;
        $errors = $this->importResults['errors'] ?? [];

        $html = '<div class="bg-green-50 border border-green-200 rounded-lg p-4">'.
            '<h3 class="text-sm font-medium text-green-800">Import Results</h3>'.
            '<div class="mt-2 text-sm text-green-700">'.
            '<p><strong>Successfully imported:</strong> '.$imported.' transactions</p>'.
            '<p><strong>Total processed:</strong> '.$total.' transactions</p>';

        if (! empty($errors)) {
            $html .= '<div class="mt-3 bg-yellow-50 border border-yellow-200 rounded p-3">'.
                '<p class="font-medium text-yellow-800">Errors:</p>'.
                '<ul class="mt-1 text-sm text-yellow-700 list-disc list-inside">';
            foreach ($errors as $error) {
                $html .= '<li>'.htmlspecialchars($error).'</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div></div>';

        return $html;
    }
}
