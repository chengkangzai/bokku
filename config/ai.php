<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used for
    | import operations and other AI-powered features. You can switch between
    | providers based on your environment or requirements.
    |
    | Supported: "ollama", "openai"
    |
    */
    'default' => env('AI_PROVIDER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each AI provider. These
    | settings will be used by the AIProviderService to dynamically
    | switch between providers based on your needs.
    |
    */
    'providers' => [
        'ollama' => [
            'enabled' => env('OLLAMA_ENABLED', true),
            'url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
            'model' => env('OLLAMA_MODEL', 'gemma3:4b'),
            'timeout' => env('OLLAMA_TIMEOUT', 60),
        ],

        'openai' => [
            'enabled' => env('OPENAI_ENABLED', false),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'timeout' => env('OPENAI_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to the AI-powered import feature.
    |
    */
    'import' => [
        // Maximum file size in MB
        'max_file_size' => env('AI_IMPORT_MAX_FILE_SIZE', 10),

        // Supported file types
        'supported_types' => [
            'csv',
            'pdf',
            'xlsx',
            'xls',
            'txt',
        ],

        // Maximum rows to process per batch
        'batch_size' => env('AI_IMPORT_BATCH_SIZE', 100),

        // Malaysian bank formats configuration
        'malaysian_banks' => [
            'date_format' => 'd/m/Y',
            'currency' => 'RM',
            'common_banks' => [
                'maybank' => 'Maybank',
                'cimb' => 'CIMB Bank',
                'public_bank' => 'Public Bank',
                'rhb' => 'RHB Bank',
                'hong_leong' => 'Hong Leong Bank',
                'ambank' => 'AmBank',
                'bank_islam' => 'Bank Islam',
                'ocbc' => 'OCBC Bank',
                'hsbc' => 'HSBC',
                'standard_chartered' => 'Standard Chartered',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Prompts Configuration
    |--------------------------------------------------------------------------
    |
    | System prompts used for various AI operations.
    |
    */
    'prompts' => [
        'transaction_extraction' => 'You are a financial data extraction specialist. Extract transaction data from the provided content and return it in a structured JSON format. Focus on identifying dates (in DD/MM/YYYY format for Malaysian banks), amounts (with RM currency), descriptions, and transaction types (income/expense). Ensure accuracy and handle various bank statement formats.',

        'column_mapping' => 'Analyze the provided CSV/Excel columns and map them to standard transaction fields: date, amount, description, type, category, reference. Consider Malaysian banking conventions and date formats (DD/MM/YYYY).',

        'category_suggestion' => 'Based on the transaction description, suggest the most appropriate category from the user\'s existing categories or recommend a new one if none match.',
    ],
];
