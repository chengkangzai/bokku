<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
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
    public function extractTransactions(string $content, string $fileType, ?string $userInstructions = null, array $existingCategories = []): array
    {
        $schema = $this->getTransactionExtractionSchema();

        $prism = $this->configurePrism(
            Prism::structured()->withSchema($schema)

        );

        // Determine if it's an image or document based on file extension
        $media = in_array(strtolower($fileType), ['jpg', 'jpeg', 'png', 'webp'])
            ? Image::fromRawContent($content)
            : Document::fromRawContent($content);

        try {
            $response = $prism->withSystemPrompt(view('ai-prompts.transaction-extraction', [
                'fileType' => $fileType,
                'userInstructions' => $userInstructions,
                'existingCategories' => $existingCategories,
            ]))
                ->withMessages([
                    new UserMessage(
                        'Extract all transactions from the attached file',
                        [$media]  // Pass as media attachment
                    ),
                ])
                ->asStructured();

            // Access the structured data from the response
            $data = $response->structured;

            if ($data === null) {
                throw new Exception('Failed to parse structured response from AI provider');
            }

            return $data;
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error('AI extraction failed', [
                'error' => $e->getMessage(),
                'provider' => $this->provider,
                'model' => $this->model,
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
                            new StringSchema('category', 'Suggested category', nullable: true),
                        ],
                        // OpenAI strict mode requires ALL properties in required array, even nullable ones
                        ['date', 'description', 'amount', 'type', 'reference', 'balance', 'category']
                    )
                ),
            ],
            ['transactions']
        );
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

        $prism = $prism->using($provider, $this->model)
            ->withClientOptions(['timeout' => $timeout]);

        // Only enable strict mode for OpenAI
        if ($this->provider === 'openai') {
            $prism = $prism->withProviderOptions([
                'schema' => [
                    'strict' => true,
                ],
            ]);
        }

        return $prism;
    }
}
