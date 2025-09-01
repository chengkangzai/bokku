<?php

use App\Models\Category;
use App\Models\User;
use App\Services\AI\AIProviderService;
use App\Services\Import\UnifiedImportHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    // Create handler with real AI service (will use Prism fake)
    $this->handler = new UnifiedImportHandler(new AIProviderService);

    // Setup auth user for tests
    $this->user = User::factory()->create();
    Auth::login($this->user);

    // Create some test categories
    Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Salary',
        'type' => 'income',
    ]);
    Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Food & Dining',
        'type' => 'expense',
    ]);
});

describe('UnifiedImportHandler File Processing', function () {
    it('can process PDF files with Malaysian bank data', function () {
        // Setup Prism fake response
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        [
                            'date' => '15/01/2024',
                            'description' => 'ATM WITHDRAWAL KL SENTRAL',
                            'amount' => 100.00,
                            'type' => 'expense',
                            'reference' => 'ATM123456',
                            'balance' => 1500.00,
                            'category' => null,
                        ],
                        [
                            'date' => '16/01/2024',
                            'description' => 'SALARY CREDIT',
                            'amount' => 3000.00,
                            'type' => 'income',
                            'balance' => 4500.00,
                            'category' => 'Salary',
                        ],
                    ],
                ])
                ->withFinishReason(FinishReason::Stop)
                ->withUsage(new Usage(100, 200))
                ->withMeta(new Meta('test-1', 'test-model')),
        ]);

        $result = $this->handler->processFile('maybank statement content', 'pdf');

        expect($result)->toHaveKey('bank_name')
            ->and($result['bank_name'])->toBe('Maybank')
            ->and($result)->toHaveKey('account_number')
            ->and($result['account_number'])->toBeNull()
            ->and($result)->toHaveKey('currency', 'MYR')
            ->and($result)->toHaveKey('transactions')
            ->and($result['transactions'])->toHaveCount(2)
            ->and($result['transactions'][0])->toMatchArray([
                'date' => '2024-01-15',
                'description' => 'WITHDRAWAL KL SENTRAL', // 'ATM ' prefix removed by cleaning
                'amount' => 100.00,
                'type' => 'expense',
                'reference' => 'ATM123456',
                'balance' => 1500.00,
                'category' => null,
            ])
            ->and($result['metadata'])->toHaveKey('total_transactions', 2)
            ->and($result['metadata'])->toHaveKey('date_range', '15 Jan 2024 - 16 Jan 2024');
    });

    it('can process CSV files', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        [
                            'date' => '20/01/2024',
                            'description' => 'GRAB PURCHASE',
                            'amount' => 25.50,
                            'type' => 'expense',
                            'reference' => null,
                            'balance' => null,
                            'category' => 'Food & Dining',
                        ],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('Date,Amount,Description\n20/01/2024,25.50,GRAB PURCHASE', 'csv');

        expect($result['transactions'])->toHaveCount(1)
            ->and($result['transactions'][0]['amount'])->toBe(25.50)
            ->and($result['transactions'][0]['type'])->toBe('expense')
            ->and($result['transactions'][0]['category'])->toBe('Food & Dining');
    });

    it('handles user instructions', function () {
        $userInstructions = 'This is a credit card statement, ignore transactions before January 10th';

        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        [
                            'date' => '12/01/2024',
                            'description' => 'VALID TRANSACTION',
                            'amount' => 50.00,
                            'type' => 'expense',
                            'reference' => null,
                            'balance' => null,
                            'category' => null,
                        ],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('Statement content', 'pdf', $userInstructions);

        expect($result['transactions'])->toHaveCount(1)
            ->and($result['transactions'][0]['description'])->toBe('VALID TRANSACTION');
    });

    it('handles AI extraction failure gracefully', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured(null), // Simulate parsing failure
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Import processing failed', \Mockery::any());

        expect(fn () => $this->handler->processFile('content', 'pdf'))
            ->toThrow(Exception::class, 'Failed to process file');
    });
});

describe('UnifiedImportHandler Transaction Formatting', function () {
    it('formats Malaysian date formats correctly', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'Test 1', 'amount' => 100, 'type' => 'expense'],
                        ['date' => '15-01-2024', 'description' => 'Test 2', 'amount' => 200, 'type' => 'expense'],
                        ['date' => '15 Jan 2024', 'description' => 'Test 3', 'amount' => 300, 'type' => 'expense'],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['date'])->toBe('2024-01-15')
            ->and($result['transactions'][1]['date'])->toBe('2024-01-15')
            ->and($result['transactions'][2]['date'])->toBe('2024-01-15');
    });

    it('cleans transaction descriptions', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'POS PURCHASE AT MERCHANT', 'amount' => 100, 'type' => 'expense'],
                        ['date' => '16/01/2024', 'description' => 'IBG TRANSFER TO ACCOUNT', 'amount' => 200, 'type' => 'expense'],
                        ['date' => '17/01/2024', 'description' => 'ATM WITHDRAWAL', 'amount' => 300, 'type' => 'expense'],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['description'])->toBe('PURCHASE AT MERCHANT')
            ->and($result['transactions'][1]['description'])->toBe('TRANSFER TO ACCOUNT')
            ->and($result['transactions'][2]['description'])->toBe('WITHDRAWAL');
    });

    it('determines transaction types from keywords', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'SALARY PAYMENT', 'amount' => 5000, 'type' => 'income'],
                        ['date' => '16/01/2024', 'description' => 'IBG TRANSFER', 'amount' => 100, 'type' => 'transfer'],
                        ['date' => '17/01/2024', 'description' => 'PURCHASE', 'amount' => 50, 'type' => 'expense'],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['type'])->toBe('income')
            ->and($result['transactions'][1]['type'])->toBe('transfer')
            ->and($result['transactions'][2]['type'])->toBe('expense');
    });

    it('skips invalid transactions', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => 'invalid', 'description' => 'Invalid Date', 'amount' => 100, 'type' => 'expense'],
                        ['date' => '15/01/2024', 'description' => '', 'amount' => 200, 'type' => 'expense'],
                        ['date' => '16/01/2024', 'description' => 'Valid', 'amount' => 0, 'type' => 'expense'],
                        ['date' => '17/01/2024', 'description' => 'Another Valid', 'amount' => 50, 'type' => 'expense'],
                    ],
                ]),
        ]);

        Log::shouldReceive('warning')->times(3); // Three invalid transactions

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'])->toHaveCount(1)
            ->and($result['transactions'][0]['description'])->toBe('Another Valid');
    });
});

describe('UnifiedImportHandler Bank Detection', function () {
    it('detects bank name from content patterns', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [],
                ]),
        ]);

        $result = $this->handler->processFile('maybank statement content', 'pdf');

        expect($result['bank_name'])->toBe('Maybank');
    });

    it('detects CIMB bank from content', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [],
                ]),
        ]);

        $result = $this->handler->processFile('This is a CIMB bank statement', 'pdf');

        expect($result['bank_name'])->toBe('CIMB Bank');
    });

    it('returns Unknown Bank when no pattern matches', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [],
                ]),
        ]);

        $result = $this->handler->processFile('Generic content without bank name', 'pdf');

        expect($result['bank_name'])->toBe('Unknown Bank');
    });
});

describe('UnifiedImportHandler Date Range Calculation', function () {
    it('calculates date range from transactions', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'First', 'amount' => 100, 'type' => 'expense'],
                        ['date' => '20/01/2024', 'description' => 'Middle', 'amount' => 200, 'type' => 'expense'],
                        ['date' => '25/01/2024', 'description' => 'Last', 'amount' => 300, 'type' => 'expense'],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['metadata']['date_range'])->toBe('15 Jan 2024 - 25 Jan 2024');
    });

    it('handles single transaction date range', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'Only', 'amount' => 100, 'type' => 'expense'],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['metadata']['date_range'])->toBe('15 Jan 2024');
    });

    it('handles empty transactions', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['metadata']['date_range'])->toBeNull();
    });
});

describe('UnifiedImportHandler Error Handling', function () {
    it('handles empty AI response gracefully', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'])->toBeEmpty()
            ->and($result['bank_name'])->toBe('Unknown Bank')
            ->and($result['account_number'])->toBeNull()
            ->and($result['currency'])->toBe('MYR');
    });
});

describe('UnifiedImportHandler Categories Integration', function () {
    it('passes existing categories to AI service', function () {
        // Create more categories
        Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Transport',
            'type' => 'expense',
        ]);

        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'GRAB', 'amount' => 25, 'type' => 'expense', 'category' => 'Transport'],
                        ['date' => '16/01/2024', 'description' => 'LUNCH', 'amount' => 15, 'type' => 'expense', 'category' => 'Food & Dining'],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['category'])->toBe('Transport')
            ->and($result['transactions'][1]['category'])->toBe('Food & Dining');
    });

    it('handles transactions without category suggestions', function () {
        Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'transactions' => [
                        ['date' => '15/01/2024', 'description' => 'UNKNOWN VENDOR', 'amount' => 100, 'type' => 'expense', 'category' => null],
                    ],
                ]),
        ]);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['category'])->toBeNull();
    });
});
