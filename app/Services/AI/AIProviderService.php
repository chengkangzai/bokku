<?php

namespace App\Services\AI;

use Exception;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AIProviderService
{
    protected string $provider;

    protected string $model;

    protected array $config;

    public function __construct()
    {
        // Get provider from app config
        $this->provider = config('app.ai_provider', 'ollama');
        $this->config = config("prism.providers.{$this->provider}", []);

        // Set model based on provider with hardcoded defaults
        $this->model = match ($this->provider) {
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
        if (! config("prism.providers.{$provider}")) {
            throw new Exception("AI provider '{$provider}' is not configured");
        }

        $this->provider = $provider;
        $this->config = config("prism.providers.{$provider}", []);
        
        // Set model based on provider with hardcoded defaults
        $this->model = match ($provider) {
            'ollama' => 'gemma3:4b',
            'openai' => 'gpt-4o-mini',
            default => 'gpt-4o-mini'
        };

        return $this;
    }

    /**
     * Extract transactions from file content
     */
    public function extractTransactions(string $content, string $fileType, ?string $userInstructions = null): array
    {
        $schema = $this->getTransactionExtractionSchema();
        $systemPrompt = $this->renderTransactionExtractionPrompt($fileType, $userInstructions);

        $prism = $this->configurePrism(
            Prism::structured()->withSchema($schema)
        );

        $response = $prism->withSystemPrompt($systemPrompt)
            ->withMessages([
                new UserMessage(
                    "Extract all transactions from the following content:\n\n".$content
                ),
            ])
            ->asStructured();

        return $response->toArray();
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

    protected function renderTransactionExtractionPrompt(string $fileType, ?string $userInstructions = null): string
    {
        return view('ai-prompts.transaction-extraction', [
            'fileType' => $fileType,
            'userInstructions' => $userInstructions,
        ])->render();
    }

    /**
     * Configure Prism instance with provider settings
     */
    protected function configurePrism($prism)
    {
        $provider = match ($this->provider) {
            'ollama' => Provider::Ollama,
            'openai' => Provider::OpenAI,
            default => Provider::OpenAI,
        };

        $timeout = match ($this->provider) {
            'ollama' => $this->config['timeout'] ?? 60,
            default => $this->config['timeout'] ?? 30,
        };

        return $prism->using($provider, $this->model)
            ->withClientOptions(['timeout' => $timeout]);
    }
}
