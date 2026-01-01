<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Services\ReceiptExtractorService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\PdfToText\Exceptions\CouldNotExtractText;
use Spatie\PdfToText\Exceptions\PdfNotFound;
use Spatie\Tags\Tag;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                Grid::make(1)
                    ->schema([
                        Section::make('Transaction Details')
                            ->schema([
                                Radio::make('type')
                                    ->required()
                                    ->options(TransactionType::class)
                                    ->inline()
                                    ->default(TransactionType::Expense)
                                    ->inlineLabel(false)
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('category_id', null))
                                    ->columnSpanFull(),

                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('RM')
                                    ->minValue(0.01)
                                    ->reactive()
                                    ->helperText(function (Get $get, $state) {
                                        $accountId = $get('account_id');
                                        $type = $get('type');
                                        $amount = (float) $state;

                                        if (! $accountId || ! $type || ! $amount) {
                                            return null;
                                        }

                                        $account = Account::find($accountId);

                                        if (! $account) {
                                            return null;
                                        }

                                        return $account->getBalanceWarningMessage($amount, $type);
                                    }),

                                DatePicker::make('date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),

                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Grocery shopping at Walmart'),
                            ])->columns([
                                'default' => 1,
                                'sm' => 2,
                            ]),

                        Section::make('Accounts & Category')
                            ->schema([
                                Select::make('account_id')
                                    ->label(fn (Get $get) => match ($get('type')) {
                                        TransactionType::Income => 'To Account',
                                        TransactionType::Expense, TransactionType::Transfer => 'From Account',
                                        default => 'Account'
                                    })
                                    ->relationship(
                                        'account',
                                        'name',
                                        fn (Builder $query) => $query->where('user_id', auth()->id())->where('is_active', true)
                                    )
                                    ->required()
                                    ->native(false)
                                    ->reactive()
                                    ->visible(fn (Get $get) => ! empty($get('type')) && in_array($get('type'), [TransactionType::Income, TransactionType::Expense, TransactionType::Transfer]))
                                    ->helperText(fn (Get $get) => empty($get('type')) ? 'Please select a transaction type first' : null
                                    ),

                                Select::make('to_account_id')
                                    ->label('To Account')
                                    ->relationship(
                                        'toAccount',
                                        'name',
                                        fn (Builder $query) => $query->where('user_id', auth()->id())->where('is_active', true)
                                    )
                                    ->required()
                                    ->native(false)
                                    ->visible(fn (Get $get) => $get('type') === TransactionType::Transfer),

                                Select::make('category_id')
                                    ->relationship(
                                        'category',
                                        'name',
                                        fn (Builder $query, Get $get) => $query->where('user_id', auth()->id())
                                            ->when($get('type'), fn ($q, $type) => $q->where('type', $type))
                                    )
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                                    ->helperText(function (Get $get, $state) {
                                        $categoryId = $state;
                                        $amount = (float) $get('amount');
                                        $type = $get('type');

                                        if (! $categoryId || ! $amount || $type !== TransactionType::Expense) {
                                            return null;
                                        }

                                        $category = Category::find($categoryId);

                                        if (! $category) {
                                            return null;
                                        }

                                        return $category->getBudgetWarning($amount);
                                    })
                                    ->createOptionForm(fn (Get $get) => [
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Groceries, Salary'),
                                        Select::make('type')
                                            ->required()
                                            ->options([
                                                TransactionType::Income->value => 'Income',
                                                TransactionType::Expense->value => 'Expense',
                                            ])
                                            ->default($get('type'))
                                            ->disabled()
                                            ->dehydrated(),
                                        ColorPicker::make('color')
                                            ->required()
                                            ->default('#6b7280'),
                                        Hidden::make('user_id')
                                            ->default(auth()->id()),
                                        Hidden::make('sort_order')
                                            ->default(0),
                                    ])
                                    ->createOptionUsing(function (array $data, Get $get) {
                                        $data['user_id'] = auth()->id();
                                        $data['type'] = $get('type');

                                        return Category::create($data)->getKey();
                                    })
                                    ->createOptionModalHeading('Create New Category')
                                    ->visible(fn (Get $get) => ! empty($get('type')) && in_array($get('type'), [TransactionType::Income, TransactionType::Expense])),

                                Select::make('payee_id')
                                    ->label('Payee')
                                    ->relationship(
                                        'payee',
                                        'name',
                                        fn (Builder $query) => $query->where('user_id', auth()->id())->where('is_active', true)
                                    )
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (! $state || $get('category_id')) {
                                            return;
                                        }

                                        $payee = Payee::find($state);

                                        if ($payee && $payee->default_category_id) {
                                            $set('category_id', $payee->default_category_id);
                                        }
                                    })
                                    ->helperText('Selecting a payee with a default category will auto-fill the category')
                                    ->visible(fn (Get $get) => ! empty($get('type')) && in_array($get('type'), [TransactionType::Income, TransactionType::Expense])),
                            ])
                            ->columns(2)
                            ->description(fn (Get $get) => empty($get('type')) ? 'Select a transaction type to see available options' : null),

                    ])
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),

                Grid::make(1)
                    ->schema([
                        Section::make('Attachments')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('receipts')
                                    ->collection('receipts')
                                    ->multiple()
                                    ->reorderable()
                                    ->maxFiles(5)
                                    ->openable()
                                    ->downloadable()
                                    ->hintActions([
                                        Action::make('Auto Fill')
                                            ->icon('heroicon-o-pencil-square')
                                            ->visible(fn (?Transaction $record, string $context) => ! empty(config('prism.providers.openai.api_key')) && $context == 'edit' && $record->getFirstMedia('receipts') !== null)
                                            ->action(function (Transaction $record, Set $set, Get $get) {
                                                try {
                                                    $media = $record->getFirstMedia('receipts');
                                                    $mimeType = $media->mime_type;

                                                    if (str_starts_with($mimeType, 'image/')) {
                                                        // For images: use streams directly
                                                        $stream = $media->stream();
                                                        $content = stream_get_contents($stream);
                                                        fclose($stream);

                                                        $extractedInfo = self::extractAndAutoFill($content, $mimeType, $set, $get);
                                                    } elseif ($mimeType === 'application/pdf') {
                                                        // For PDFs: must use temp files
                                                        $tempPath = storage_path('app/temp_'.uniqid().'.pdf');
                                                        try {
                                                            $stream = $media->stream();
                                                            file_put_contents($tempPath, stream_get_contents($stream));
                                                            fclose($stream);

                                                            $extractedInfo = self::extractAndAutoFill($tempPath, $mimeType, $set, $get);
                                                        } finally {
                                                            if (file_exists($tempPath)) {
                                                                unlink($tempPath);
                                                            }
                                                        }
                                                    } else {
                                                        throw new \Exception('Unsupported file type: '.$mimeType);
                                                    }

                                                    if (! empty($extractedInfo)) {
                                                        Notification::make()
                                                            ->title('Information Extracted')
                                                            ->body('Successfully extracted '.implode(', ', $extractedInfo).'.')
                                                            ->success()
                                                            ->send();
                                                    }
                                                } catch (CouldNotExtractText $e) {
                                                    \Log::warning('PDF text extraction failed', [
                                                        'transaction_id' => $record->id,
                                                        'error' => $e->getMessage(),
                                                    ]);
                                                    Notification::make()
                                                        ->title('PDF Extraction Failed')
                                                        ->body('Could not extract text from the PDF file. Please check if the PDF is valid.')
                                                        ->danger()
                                                        ->send();
                                                } catch (PdfNotFound $e) {
                                                    \Log::error('PDF file not found during extraction', [
                                                        'transaction_id' => $record->id,
                                                        'error' => $e->getMessage(),
                                                    ]);
                                                    Notification::make()
                                                        ->title('File Not Found')
                                                        ->body('The PDF file could not be found. Please try uploading again.')
                                                        ->danger()
                                                        ->send();
                                                } catch (\Exception $e) {
                                                    \Log::error('Receipt extraction failed', [
                                                        'transaction_id' => $record->id,
                                                        'error' => $e->getMessage(),
                                                        'trace' => $e->getTraceAsString(),
                                                    ]);
                                                    Notification::make()
                                                        ->title('Extraction Failed')
                                                        ->body('Failed to extract receipt information. Please fill in the details manually.')
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    ])
                                    ->afterStateUpdated(function (array $state, Set $set, Get $get, string $context) {
                                        if ($context !== 'create' || empty(config('prism.providers.openai.api_key'))) {
                                            return;
                                        }

                                        try {
                                            $uploadedFile = $state[0];
                                            $mimeType = $uploadedFile->getMimeType();

                                            if (str_starts_with($mimeType, 'image/')) {
                                                // For images: read content directly
                                                $content = file_get_contents($uploadedFile->path());
                                                $extractedInfo = self::extractAndAutoFill($content, $mimeType, $set, $get);
                                            } elseif ($mimeType === 'application/pdf') {
                                                // For PDFs: use file path
                                                $extractedInfo = self::extractAndAutoFill($uploadedFile->path(), $mimeType, $set, $get);
                                            } else {
                                                throw new \Exception('Unsupported file type: '.$mimeType);
                                            }

                                            if (! empty($extractedInfo)) {
                                                Notification::make()
                                                    ->title('Receipt Information Extracted')
                                                    ->body('Successfully extracted '.implode(' and ', $extractedInfo).'.')
                                                    ->success()
                                                    ->send();
                                            }
                                        } catch (CouldNotExtractText $e) {
                                            \Log::warning('PDF text extraction failed during upload', [
                                                'file_name' => $uploadedFile->getClientOriginalName(),
                                                'error' => $e->getMessage(),
                                            ]);
                                            Notification::make()
                                                ->title('PDF Extraction Failed')
                                                ->body('Could not extract text from the PDF. You can fill in the details manually.')
                                                ->warning()
                                                ->send();
                                        } catch (PdfNotFound $e) {
                                            \Log::error('PDF file not found during upload extraction', [
                                                'file_name' => $uploadedFile->getClientOriginalName(),
                                                'error' => $e->getMessage(),
                                            ]);
                                            Notification::make()
                                                ->title('File Processing Error')
                                                ->body('Could not process the PDF file. Please try uploading again.')
                                                ->danger()
                                                ->send();
                                        } catch (\Exception $e) {
                                            \Log::error('Receipt extraction failed during upload', [
                                                'file_name' => $uploadedFile->getClientOriginalName(),
                                                'mime_type' => $uploadedFile->getMimeType(),
                                                'error' => $e->getMessage(),
                                                'trace' => $e->getTraceAsString(),
                                            ]);
                                            Notification::make()
                                                ->title('Auto-extraction Unavailable')
                                                ->body('Could not automatically extract receipt information. Please fill in the details manually.')
                                                ->warning()
                                                ->send();
                                        }
                                    })
                                    ->acceptedFileTypes([
                                        'image/jpeg',
                                        'image/png',
                                        'image/gif',
                                        'image/webp',
                                        'application/pdf',
                                    ])
                                    ->maxSize(5120) // 5MB in KB
                                    ->label('Upload Receipts')
                                    ->helperText(! empty(config('prism.providers.openai.api_key'))
                                        ? 'Upload receipts, invoices, or related documents (max 5 files, 5MB each)'
                                        : 'Upload receipts, invoices, or related documents (max 5 files, 5MB each). Auto-extraction is currently unavailable - please configure OpenAI API key.'
                                    )
                                    ->columnSpanFull()
                                    ->conversion('thumb')
                                    ->conversionsDisk('s3'),
                            ]),

                        Section::make('Additional Information')
                            ->schema([
                                TextInput::make('reference')
                                    ->maxLength(255)
                                    ->placeholder('Check number, invoice #, etc.'),

                                Textarea::make('notes')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),

                                SpatieTagsInput::make('tags')
                                    ->type(fn () => 'user_'.auth()->id())
                                    ->suggestions(function () {
                                        return Tag::getWithType('user_'.auth()->id())->pluck('name');
                                    })
                                    ->columnSpanFull()
                                    ->placeholder('Add tags to organize transactions'),

                                Toggle::make('is_reconciled')
                                    ->label('Reconciled')
                                    ->helperText('Mark as reconciled when verified against bank statement'),
                            ]),

                        Section::make('Automation')
                            ->schema([
                                TextEntry::make('matching_rules')
                                    ->label('Matching Rules')
                                    ->state(function ($get) {
                                        $description = $get('description');
                                        $amount = $get('amount');
                                        $type = $get('type');

                                        if (! $description && ! $amount) {
                                            return 'Enter description or amount to see matching rules';
                                        }

                                        // Find matching rules
                                        $rules = TransactionRule::where('user_id', auth()->id())
                                            ->where('is_active', true)
                                            ->where(function ($query) use ($type) {
                                                $query->where('apply_to', 'all')
                                                    ->orWhere('apply_to', $type->value);
                                            })
                                            ->orderBy('priority', 'desc')
                                            ->get();

                                        $matchingRules = [];
                                        foreach ($rules as $rule) {
                                            // Create a temporary transaction object for matching
                                            $tempTransaction = new Transaction([
                                                'description' => $description ?? '',
                                                'amount' => $amount ?? 0,
                                                'type' => $type ?? TransactionType::Expense,
                                                'category_id' => $get('category_id'),
                                                'user_id' => auth()->id(),
                                            ]);

                                            if ($rule->matches($tempTransaction)) {
                                                $matchingRules[] = $rule->name;
                                            }
                                        }

                                        if (empty($matchingRules)) {
                                            return 'No matching rules found';
                                        }

                                        return '✓ Will apply: '.implode(', ', $matchingRules);
                                    })
                                    ->helperText('Rules will apply automatically when saving')
                                    ->visible(fn ($operation) => $operation === 'create'),
                            ])
                            ->visible(fn ($operation) => $operation === 'create'),
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('amount')
                    ->money('myr')
                    ->sortable()
                    ->color(fn (Transaction $record) => $record->type->getColor()),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->visible(fn () => true),

                TextColumn::make('category.name')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('payee.name')
                    ->label('Payee')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('toAccount.name')
                    ->label('To')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_reconciled')
                    ->boolean()
                    ->label('✓'),

                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('appliedRule.name')
                    ->label('Applied Rule')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                SpatieTagsColumn::make('tags')
                    ->type(fn () => 'user_'.auth()->id())
                    ->toggleable(),

                SpatieMediaLibraryImageColumn::make('receipts')
                    ->collection('receipts')
                    ->label('Attachments')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(TransactionType::class),

                SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship(
                        'account',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship(
                        'category',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                SelectFilter::make('payee_id')
                    ->label('Payee')
                    ->relationship(
                        'payee',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->placeholder('All transactions')
                    ->trueLabel('Reconciled only')
                    ->falseLabel('Unreconciled only'),
            ])
            ->recordActions([
                Action::make('apply_rules')
                    ->label('Apply Rules')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Apply Rules')
                    ->modalDescription('This will apply matching automation rules to this transaction.')
                    ->action(function ($record) {
                        TransactionRule::applyRules($record);
                        $record->refresh();

                        if ($record->applied_rule_id) {
                            Notification::make()
                                ->title('Rules Applied')
                                ->success()
                                ->body("Applied rule: {$record->appliedRule->name}")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No Rules Applied')
                                ->warning()
                                ->body('No matching rules found for this transaction.')
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => ! $record->applied_rule_id),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('apply_rules_bulk')
                        ->label('Apply Rules')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Apply Rules to Selected Transactions')
                        ->modalDescription('This will apply matching automation rules to all selected transactions.')
                        ->action(function ($records) {
                            $applied = 0;
                            $skipped = 0;

                            foreach ($records as $transaction) {
                                if (! $transaction->applied_rule_id) {
                                    TransactionRule::applyRules($transaction);
                                    $transaction->refresh();

                                    if ($transaction->applied_rule_id) {
                                        $applied++;
                                    } else {
                                        $skipped++;
                                    }
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title('Rules Applied')
                                ->success()
                                ->body("Applied rules to {$applied} transaction(s). Skipped {$skipped}.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('user_id', auth()->id())
            ->whereDate('date', today())
            ->count();

        return $count > 0 ? $count : null;
    }

    private static function extractAndAutoFill(string $input, string $mimeType, Set $set, Get $get): array
    {
        $extractor = (new ReceiptExtractorService)->extractInformation($input, $mimeType);
        $extractedInfo = [];

        if (! $extractor) {
            return $extractedInfo;
        }

        // Extract transaction type
        if (! empty($extractor['type']) && empty($get('type'))) {
            $type = $extractor['type'];
            if (in_array($type, [TransactionType::Income->value, TransactionType::Expense->value, TransactionType::Transfer->value])) {
                $set('type', $type);
                $extractedInfo[] = 'type';
            }
        }

        // Extract amount
        if (! empty($extractor['amount']) && empty($get('amount'))) {
            $amount = (float) $extractor['amount'];
            if ($amount > 0) {
                $set('amount', $amount);
                $extractedInfo[] = 'amount';
            }
        }

        // Extract date
        if (! empty($extractor['date']) && $extractor['date'] !== 'N/A' && empty($get('date'))) {
            $date = $extractor['date'];
            // Validate date format and ensure it's not in the future
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
                if ($dateObj && $dateObj->format('Y-m-d') === $date && $dateObj <= new \DateTime) {
                    $set('date', $date);
                    $extractedInfo[] = 'date';
                }
            }
        }

        // Extract description
        if (! empty($extractor['description']) && empty($get('description'))) {
            $description = trim($extractor['description']);
            if (strlen($description) > 0 && strlen($description) <= 255) {
                $set('description', $description);
                $extractedInfo[] = 'description';
            }
        }

        // Extract category
        if (! empty($extractor['category_id']) && empty($get('category_id'))) {
            $categoryId = $extractor['category_id'];
            // Verify category exists and belongs to the user
            $category = Category::where('id', $categoryId)
                ->where('user_id', auth()->id())
                ->first();
            if ($category) {
                $set('category_id', $categoryId);
                $extractedInfo[] = 'category';
            }
        }

        return $extractedInfo;
    }
}
