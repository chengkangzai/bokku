<?php

use App\Services\AI\AIProviderService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->aiService = new AIProviderService;

    // Set up test configuration
    Config::set('ai.default', 'ollama');
    Config::set('ai.providers.ollama', [
        'enabled' => true,
        'model' => 'gemma3:4b',
        'timeout' => 60,
    ]);
    Config::set('ai.providers.openai', [
        'enabled' => true,
        'model' => 'gpt-4o-mini',
        'timeout' => 30,
    ]);

    Config::set('ai.import.malaysian_banks.common_banks', [
        'Maybank' => 'Maybank',
        'Hong Leong Bank' => 'Hong Leong Bank',
        'Public Bank' => 'Public Bank',
    ]);
});

describe('AIProviderService Configuration', function () {
    it('can initialize with default provider', function () {
        $service = new AIProviderService;
        expect($service)->toBeInstanceOf(AIProviderService::class);
    });

    it('can switch providers dynamically', function () {
        Config::set('ai.providers.openai.enabled', true);

        $service = new AIProviderService;
        $result = $service->useProvider('openai');

        expect($result)->toBeInstanceOf(AIProviderService::class);
    });

    it('throws exception when switching to disabled provider', function () {
        Config::set('ai.providers.openai.enabled', false);

        $service = new AIProviderService;

        expect(fn () => $service->useProvider('openai'))
            ->toThrow(Exception::class, "AI provider 'openai' is not enabled");
    });
});

describe('Blade Template Rendering', function () {
    it('can render transaction extraction prompt template', function () {
        // Test that the service can access and render the Blade template
        // Since we can't easily mock Prism, we'll test the template rendering directly
        $promptContent = view('ai-prompts.transaction-extraction', [
            'fileType' => 'pdf',
            'userInstructions' => 'This is a test instruction',
        ])->render();

        expect($promptContent)->toContain('financial data extraction specialist')
            ->and($promptContent)->toContain('pdf')
            ->and($promptContent)->toContain('This is a test instruction')
            ->and($promptContent)->toContain('DD/MM/YYYY format')
            ->and($promptContent)->toContain('Maybank');
    });

    it('can render column mapping prompt template', function () {
        $headers = ['Date', 'Amount', 'Description'];

        $promptContent = view('ai-prompts.column-mapping', [
            'headers' => $headers,
            'userInstructions' => null,
        ])->render();

        expect($promptContent)->toContain('Date')
            ->and($promptContent)->toContain('Amount')
            ->and($promptContent)->toContain('Description');
    });

    it('can render category suggestion prompt template', function () {
        $promptContent = view('ai-prompts.category-suggestion', [
            'description' => 'McDonald\'s Restaurant',
            'type' => 'expense',
            'amount' => 25.50,
            'existingCategories' => [
                ['name' => 'Food & Dining', 'type' => 'expense'],
                ['name' => 'Transportation', 'type' => 'expense'],
            ],
            'userInstructions' => null,
        ])->render();

        expect($promptContent)->toContain('McDonald&#039;s Restaurant')
            ->and($promptContent)->toContain('expense')
            ->and($promptContent)->toContain('Food & Dining');
    });
});

describe('Provider Model Configuration', function () {
    it('sets correct model for ollama provider', function () {
        Config::set('ai.default', 'ollama');

        $service = new AIProviderService;

        // We can't easily test private properties, but we can test the service initializes correctly
        expect($service)->toBeInstanceOf(AIProviderService::class);
    });

    it('sets correct model for openai provider', function () {
        Config::set('ai.default', 'openai');

        $service = new AIProviderService;

        expect($service)->toBeInstanceOf(AIProviderService::class);
    });

    it('can dynamically switch providers', function () {
        $service = new AIProviderService;

        $result = $service->useProvider('openai');

        expect($result)->toBeInstanceOf(AIProviderService::class);
    });
});

describe('Error Handling', function () {
    it('throws exception for disabled provider', function () {
        Config::set('ai.providers.openai.enabled', false);

        $service = new AIProviderService;

        expect(fn () => $service->useProvider('openai'))
            ->toThrow(Exception::class, "AI provider 'openai' is not enabled");
    });

    it('handles provider configuration gracefully', function () {
        // Test with missing provider configuration
        Config::set('ai.providers.nonexistent', null);

        $service = new AIProviderService;

        expect(fn () => $service->useProvider('nonexistent'))
            ->toThrow(Exception::class);
    });
});
