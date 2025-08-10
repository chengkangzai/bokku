<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AIProviderService
{
    protected string $provider;

    protected string $model;

    protected array $config;

    public function __construct()
    {
        $this->provider = config('ai.default', 'ollama');
        $this->config = config("ai.providers.{$this->provider}", []);

        // Set the model based on the provider
        $this->model = $this->config['model'] ?? match ($this->provider) {
            'ollama' => 'gemma3:4b',
            'openai' => 'gpt-4o-mini',
            default => 'gpt-4o-mini'
        };
    }

    /**
     * Switch to a different provider dynamically
     */
    public function useProvider(string $provider): self
    {
        if (! config("ai.providers.{$provider}.enabled", false)) {
            throw new Exception("AI provider '{$provider}' is not enabled");
        }

        $this->provider = $provider;
        $this->config = config("ai.providers.{$provider}", []);
        $this->model = $this->config['model'] ?? 'gpt-4o-mini';

        return $this;
    }

    /**
     * Extract transactions from file content
     */
    public function extractTransactions(string $content, string $fileType, ?string $userInstructions = null): array
    {
        try {
            $schema = $this->getTransactionExtractionSchema();
            $systemPrompt = $this->renderTransactionExtractionPrompt($fileType, $userInstructions);

            $prism = Prism::structured()
                ->withSchema($schema);

            // Configure provider
            if ($this->provider === 'ollama') {
                $prism->using(Provider::Ollama, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 60]);
            } else {
                $prism->using(Provider::OpenAI, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 30]);
            }

            $response = $prism->withSystemPrompt($systemPrompt)
                ->withMessages([
                    new UserMessage(
                        "Extract all transactions from the following content:\n\n".$content
                    ),
                ])
                ->asStructured();

            return $response->toArray();

        } catch (Exception $e) {
            Log::error('AI transaction extraction failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Map CSV/Excel columns to transaction fields
     */
    public function mapColumns(array $headers, ?string $userInstructions = null): array
    {
        try {
            $schema = $this->getColumnMappingSchema();
            $systemPrompt = $this->renderColumnMappingPrompt($headers, $userInstructions);

            $prism = Prism::structured()
                ->withSchema($schema);

            // Configure provider
            if ($this->provider === 'ollama') {
                $prism->using(Provider::Ollama, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 60]);
            } else {
                $prism->using(Provider::OpenAI, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 30]);
            }

            $response = $prism->withSystemPrompt($systemPrompt)
                ->withPrompt('Analyze and map the provided column headers.')
                ->asStructured();

            return $response->toArray();

        } catch (Exception $e) {
            Log::error('AI column mapping failed', [
                'provider' => $this->provider,
                'headers' => $headers,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Suggest category for a transaction
     */
    public function suggestCategory(string $description, string $type, float $amount, array $existingCategories, ?string $userInstructions = null): string
    {
        try {
            $systemPrompt = $this->renderCategorySuggestionPrompt($description, $type, $amount, $existingCategories, $userInstructions);

            $prism = Prism::text();

            // Configure provider
            if ($this->provider === 'ollama') {
                $prism->using(Provider::Ollama, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 60]);
            } else {
                $prism->using(Provider::OpenAI, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 30]);
            }

            $response = $prism->withSystemPrompt($systemPrompt)
                ->withPrompt('Suggest the most appropriate category for this transaction.')
                ->withMaxTokens(100)
                ->asText();

            return trim($response->text);

        } catch (Exception $e) {
            Log::error('AI category suggestion failed', [
                'provider' => $this->provider,
                'description' => $description,
                'error' => $e->getMessage(),
            ]);

            return 'Uncategorized';
        }
    }

    /**
     * Analyze document for bank information
     */
    public function analyzeBankDocument(string $content): array
    {
        try {
            $schema = $this->getBankAnalysisSchema();

            $prism = Prism::structured()
                ->withSchema($schema);

            // Configure provider
            if ($this->provider === 'ollama') {
                $prism->using(Provider::Ollama, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 60]);
            } else {
                $prism->using(Provider::OpenAI, $this->model)
                    ->withClientOptions(['timeout' => $this->config['timeout'] ?? 30]);
            }

            $response = $prism->withSystemPrompt(
                'You are a bank statement analyst. Identify the bank, account information, and statement period from the document.'
            )
                ->withPrompt("Analyze this bank document:\n\n".$content)
                ->asStructured();

            return $response->toArray();

        } catch (Exception $e) {
            Log::error('AI bank document analysis failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get schema for transaction extraction
     */
    protected function getTransactionExtractionSchema(): ObjectSchema
    {
        return new ObjectSchema(
            'transaction_data',
            'Extracted transaction data from bank statements',
            [
                new StringSchema('bank_name', 'Name of the bank', nullable: true),
                new StringSchema('account_number', 'Account number (masked for security)', nullable: true),
                new StringSchema('statement_period', 'Statement period (e.g., "Jan 2024")', nullable: true),
                new ArraySchema(
                    'transactions',
                    'List of extracted transactions',
                    new ObjectSchema(
                        'transaction',
                        'Individual transaction',
                        [
                            new StringSchema('date', 'Transaction date in DD/MM/YYYY format'),
                            new StringSchema('description', 'Transaction description'),
                            new NumberSchema('amount', 'Transaction amount (positive for income, negative for expense)'),
                            new StringSchema('type', 'Transaction type: income, expense, or transfer'),
                            new StringSchema('reference', 'Transaction reference number', nullable: true),
                            new NumberSchema('balance', 'Balance after transaction', nullable: true),
                            new StringSchema('category_suggestion', 'Suggested category', nullable: true),
                        ],
                        ['date', 'description', 'amount', 'type']
                    )
                ),
            ],
            ['transactions']
        );
    }

    /**
     * Get schema for column mapping
     */
    protected function getColumnMappingSchema(): ObjectSchema
    {
        return new ObjectSchema(
            'column_mapping',
            'Mapping of CSV/Excel columns to transaction fields',
            [
                new StringSchema('date_column', 'Column containing transaction date', nullable: true),
                new StringSchema('amount_column', 'Column containing transaction amount', nullable: true),
                new StringSchema('description_column', 'Column containing transaction description', nullable: true),
                new StringSchema('type_column', 'Column containing transaction type', nullable: true),
                new StringSchema('category_column', 'Column containing category', nullable: true),
                new StringSchema('reference_column', 'Column containing reference number', nullable: true),
                new StringSchema('balance_column', 'Column containing account balance', nullable: true),
                new StringSchema('debit_column', 'Column containing debit amounts', nullable: true),
                new StringSchema('credit_column', 'Column containing credit amounts', nullable: true),
                new BooleanSchema('has_separate_debit_credit', 'Whether amounts are in separate debit/credit columns'),
                new StringSchema('detected_bank', 'Detected bank based on column patterns', nullable: true),
                new StringSchema('date_format', 'Detected date format (e.g., "DD/MM/YYYY")', nullable: true),
            ],
            []
        );
    }

    /**
     * Get schema for bank document analysis
     */
    protected function getBankAnalysisSchema(): ObjectSchema
    {
        return new ObjectSchema(
            'bank_analysis',
            'Bank document analysis results',
            [
                new StringSchema('bank_name', 'Identified bank name'),
                new StringSchema('account_type', 'Type of account (e.g., savings, current)', nullable: true),
                new StringSchema('account_number', 'Masked account number', nullable: true),
                new StringSchema('statement_start_date', 'Statement start date', nullable: true),
                new StringSchema('statement_end_date', 'Statement end date', nullable: true),
                new StringSchema('currency', 'Currency used (e.g., RM, USD)'),
                new NumberSchema('opening_balance', 'Opening balance', nullable: true),
                new NumberSchema('closing_balance', 'Closing balance', nullable: true),
                new BooleanSchema('is_malaysian_bank', 'Whether this is a Malaysian bank'),
            ],
            ['bank_name', 'currency', 'is_malaysian_bank']
        );
    }

    /**
     * Render transaction extraction prompt from Blade template
     */
    protected function renderTransactionExtractionPrompt(string $fileType, ?string $userInstructions = null): string
    {
        return view('ai-prompts.transaction-extraction', [
            'fileType' => $fileType,
            'userInstructions' => $userInstructions,
        ])->render();
    }

    /**
     * Render column mapping prompt from Blade template
     */
    protected function renderColumnMappingPrompt(array $headers, ?string $userInstructions = null): string
    {
        return view('ai-prompts.column-mapping', [
            'headers' => $headers,
            'userInstructions' => $userInstructions,
        ])->render();
    }

    /**
     * Render category suggestion prompt from Blade template
     */
    protected function renderCategorySuggestionPrompt(string $description, string $type, float $amount, array $existingCategories, ?string $userInstructions = null): string
    {
        return view('ai-prompts.category-suggestion', [
            'description' => $description,
            'type' => $type,
            'amount' => $amount,
            'existingCategories' => $existingCategories,
            'userInstructions' => $userInstructions,
        ])->render();
    }
}
