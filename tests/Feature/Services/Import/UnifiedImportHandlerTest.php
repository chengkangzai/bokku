<?php

use App\Services\AI\AIProviderService;
use App\Services\Import\UnifiedImportHandler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Mock the AIProviderService
    $this->aiService = Mockery::mock(AIProviderService::class);
    $this->handler = new UnifiedImportHandler($this->aiService);

    // Set up test configuration for Malaysian banks
    Config::set('ai.import.malaysian_banks.common_banks', [
        'maybank' => 'Maybank',
        'cimb' => 'CIMB Bank',
        'public' => 'Public Bank',
        'hong leong' => 'Hong Leong Bank',
    ]);
});

describe('UnifiedImportHandler File Processing', function () {
    it('can process PDF files with Malaysian bank data', function () {
        // Mock AI service response
        $mockAIResponse = [
            'bank_name' => 'Maybank',
            'account_number' => '1234567890',
            'statement_period' => 'January 2024',
            'currency' => 'RM',
            'transactions' => [
                [
                    'date' => '15/01/2024',
                    'description' => 'ATM WITHDRAWAL KL SENTRAL',
                    'amount' => 100.00,
                    'type' => 'expense',
                    'reference' => 'ATM123456',
                    'balance' => 1500.00,
                ],
                [
                    'date' => '16/01/2024',
                    'description' => 'SALARY CREDIT',
                    'amount' => 3000.00,
                    'type' => 'income',
                    'balance' => 4500.00,
                ],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->with('PDF bank statement content', 'pdf', null)
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('PDF bank statement content', 'pdf');

        expect($result)->toHaveKey('bank_name', 'Maybank')
            ->and($result)->toHaveKey('account_number', '******7890')
            ->and($result)->toHaveKey('currency', 'RM')
            ->and($result)->toHaveKey('transactions')
            ->and($result['transactions'])->toHaveCount(2)
            ->and($result['transactions'][0])->toMatchArray([
                'date' => '2024-01-15',
                'description' => 'WITHDRAWAL KL SENTRAL', // 'ATM ' prefix removed by cleaning
                'amount' => 100.00,
                'type' => 'expense',
            ])
            ->and($result['metadata'])->toHaveKey('total_transactions', 2)
            ->and($result['metadata'])->toHaveKey('file_type', 'pdf');
    });

    it('can process CSV files with different formats', function () {
        $mockAIResponse = [
            'bank_name' => 'CIMB Bank',
            'account_number' => '9876543210',
            'transactions' => [
                [
                    'date' => '20/01/2024',
                    'description' => 'GRAB PURCHASE',
                    'amount' => 25.50,
                    'type' => 'expense',
                    'category_suggestion' => 'Transportation',
                ],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->with('Date,Amount,Description\n20/01/2024,25.50,GRAB PURCHASE', 'csv', null)
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('Date,Amount,Description\n20/01/2024,25.50,GRAB PURCHASE', 'csv');

        expect($result)->toHaveKey('bank_name', 'CIMB Bank')
            ->and($result['transactions'])->toHaveCount(1)
            ->and($result['transactions'][0])->toMatchArray([
                'date' => '2024-01-20',
                'description' => 'GRAB PURCHASE',
                'amount' => 25.50,
                'type' => 'expense',
            ]);
    });

    it('handles user instructions parameter correctly', function () {
        $mockAIResponse = [
            'transactions' => [
                [
                    'date' => '10/01/2024',
                    'description' => 'CREDIT CARD PAYMENT',
                    'amount' => 500.00,
                    'type' => 'expense',
                ],
            ],
        ];

        $userInstructions = 'This is a credit card statement, ignore transactions before January 10th';

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->with('Statement content', 'pdf', $userInstructions)
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('Statement content', 'pdf', $userInstructions);

        expect($result['transactions'])->toHaveCount(1)
            ->and($result['metadata'])->toHaveKey('file_type', 'pdf');
    });

    it('handles AI service exceptions gracefully', function () {
        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andThrow(new Exception('AI service timeout'));

        Log::shouldReceive('error')
            ->once()
            ->with('Unified import handler failed', Mockery::type('array'));

        expect(fn () => $this->handler->processFile('content', 'pdf'))
            ->toThrow(Exception::class, 'Failed to process file: AI service timeout');
    });
});

describe('UnifiedImportHandler Transaction Formatting', function () {
    it('formats transactions correctly with Malaysian date format', function () {
        $mockAIResponse = [
            'transactions' => [
                [
                    'date' => '15/01/2024',
                    'description' => '  MAYBANK ATM WITHDRAWAL   KL SENTRAL  ',
                    'amount' => '100.00',
                    'type' => 'expense',
                    'balance' => 1500,
                ],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0])->toMatchArray([
            'date' => '2024-01-15',
            'description' => 'MAYBANK ATM WITHDRAWAL KL SENTRAL',
            'amount' => 100.00,
            'type' => 'expense',
        ]);
    });

    it('determines transaction type from description keywords', function () {
        $mockAIResponse = [
            'transactions' => [
                [
                    'date' => '15/01/2024',
                    'description' => 'SALARY PAYMENT COMPANY XYZ',
                    'amount' => 3000.00,
                    'type' => null, // AI didn't determine type
                ],
                [
                    'date' => '16/01/2024',
                    'description' => 'TRANSFER TO SAVINGS ACCOUNT',
                    'amount' => 500.00,
                    'type' => null,
                ],
                [
                    'date' => '17/01/2024',
                    'description' => 'RESTAURANT BILL PAYMENT',
                    'amount' => 50.00,
                    'type' => null,
                ],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['type'])->toBe('income')  // salary keyword
            ->and($result['transactions'][1]['type'])->toBe('transfer') // transfer keyword
            ->and($result['transactions'][2]['type'])->toBe('income'); // positive amount = income by default
    });

    it('parses various Malaysian date formats', function () {
        $mockAIResponse = [
            'transactions' => [
                ['date' => '15/01/2024', 'description' => 'Test 1', 'amount' => 100, 'type' => 'expense'],
                ['date' => '15-01-2024', 'description' => 'Test 2', 'amount' => 100, 'type' => 'expense'],
                ['date' => '15/01/24', 'description' => 'Test 3', 'amount' => 100, 'type' => 'expense'],
                ['date' => '15 Jan 2024', 'description' => 'Test 4', 'amount' => 100, 'type' => 'expense'],
                ['date' => '2024-01-15', 'description' => 'Test 5', 'amount' => 100, 'type' => 'expense'],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        foreach ($result['transactions'] as $transaction) {
            expect($transaction['date'])->toBe('2024-01-15');
        }
    });

    it('handles amount parsing with RM currency symbols', function () {
        $mockAIResponse = [
            'transactions' => [
                ['date' => '15/01/2024', 'description' => 'Test 1', 'amount' => 'RM 1,250.75', 'type' => 'expense'],
                ['date' => '16/01/2024', 'description' => 'Test 2', 'amount' => '-RM 500.00', 'type' => 'expense'],
                ['date' => '17/01/2024', 'description' => 'Test 3', 'amount' => '1250.75', 'type' => 'expense'],
                ['date' => '18/01/2024', 'description' => 'Test 4', 'amount' => 100, 'type' => 'expense'],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['amount'])->toBe(1250.75)
            ->and($result['transactions'][1]['amount'])->toBe(500.00) // Absolute value
            ->and($result['transactions'][2]['amount'])->toBe(1250.75)
            ->and($result['transactions'][3]['amount'])->toBe(100.00);
    });

    it('skips invalid transactions and continues processing', function () {
        $mockAIResponse = [
            'transactions' => [
                ['date' => '15/01/2024', 'description' => 'Valid Transaction', 'amount' => 100, 'type' => 'expense'],
                ['date' => 'invalid-date', 'description' => 'Invalid Transaction', 'amount' => 200, 'type' => 'expense'],
                ['date' => '17/01/2024', 'description' => '', 'amount' => 300, 'type' => 'expense'], // Empty description
                ['date' => '18/01/2024', 'description' => 'Another Valid', 'amount' => 400, 'type' => 'expense'],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        Log::shouldReceive('warning')->times(2); // Two invalid transactions

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'])->toHaveCount(2)
            ->and($result['transactions'][0]['description'])->toBe('Valid Transaction')
            ->and($result['transactions'][1]['description'])->toBe('Another Valid');
    });
});

describe('UnifiedImportHandler Bank Detection', function () {
    it('detects bank name from AI response', function () {
        $mockAIResponse = [
            'bank_name' => 'Maybank',
            'transactions' => [],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['bank_name'])->toBe('Maybank');
    });

    it('detects bank name from content patterns when AI fails', function () {
        $mockAIResponse = [
            'bank_name' => null,
            'transactions' => [],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $content = 'CIMB BANK BERHAD Account Statement for period ending 31/01/2024';
        $result = $this->handler->processFile($content, 'pdf');

        expect($result['bank_name'])->toBe('CIMB Bank');
    });

    it('returns unknown bank when detection fails', function () {
        $mockAIResponse = [
            'bank_name' => null,
            'transactions' => [],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('Unrecognizable bank content', 'pdf');

        expect($result['bank_name'])->toBe('Unknown Bank');
    });
});

describe('UnifiedImportHandler Account Number Masking', function () {
    it('masks account numbers correctly', function () {
        $mockAIResponse = [
            'account_number' => '1234567890123',
            'transactions' => [],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['account_number'])->toBe('*********0123');
    });

    it('handles short account numbers', function () {
        $mockAIResponse = [
            'account_number' => '1234',
            'transactions' => [],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['account_number'])->toBe('1234');
    });
});

describe('UnifiedImportHandler Date Range Calculation', function () {
    it('calculates date range from transactions', function () {
        $mockAIResponse = [
            'transactions' => [
                ['date' => '15/01/2024', 'description' => 'First', 'amount' => 100, 'type' => 'expense'],
                ['date' => '20/01/2024', 'description' => 'Middle', 'amount' => 200, 'type' => 'expense'],
                ['date' => '25/01/2024', 'description' => 'Last', 'amount' => 300, 'type' => 'expense'],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['metadata']['date_range'])->toBe('15 Jan 2024 - 25 Jan 2024');
    });

    it('handles single transaction date range', function () {
        $mockAIResponse = [
            'transactions' => [
                ['date' => '15/01/2024', 'description' => 'Only one', 'amount' => 100, 'type' => 'expense'],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['metadata']['date_range'])->toBe('15 Jan 2024');
    });
});

describe('UnifiedImportHandler Validation', function () {
    it('validates required transaction fields', function () {
        $transactions = [
            ['date' => '15/01/2024', 'description' => 'Valid', 'amount' => 100, 'type' => 'expense'],
            ['description' => 'Missing date', 'amount' => 100, 'type' => 'expense'],
            ['date' => '15/01/2024', 'amount' => 100, 'type' => 'expense'], // Missing description
            ['date' => '15/01/2024', 'description' => 'Missing amount', 'type' => 'expense'],
            ['date' => '15/01/2024', 'description' => 'Invalid type', 'amount' => 100, 'type' => 'invalid'],
        ];

        $errors = $this->handler->validate($transactions);

        expect($errors)->toHaveKey(1)
            ->and($errors[1])->toContain('Date is required')
            ->and($errors)->toHaveKey(2)
            ->and($errors[2])->toContain('Description is required')
            ->and($errors)->toHaveKey(3)
            ->and($errors[3])->toContain('Valid amount is required')
            ->and($errors)->toHaveKey(4)
            ->and($errors[4])->toContain('Valid transaction type is required');
    });

    it('validates date formats in validation', function () {
        $transactions = [
            ['date' => 'invalid-date', 'description' => 'Test', 'amount' => 100, 'type' => 'expense'],
        ];

        $errors = $this->handler->validate($transactions);

        expect($errors)->toHaveKey(0)
            ->and($errors[0])->toContain('Invalid date format');
    });

    it('returns empty array for valid transactions', function () {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'Valid transaction', 'amount' => 100, 'type' => 'expense'],
        ];

        $errors = $this->handler->validate($transactions);

        expect($errors)->toBeEmpty();
    });
});

describe('UnifiedImportHandler Description Cleaning', function () {
    it('cleans transaction descriptions', function () {
        $mockAIResponse = [
            'transactions' => [
                ['date' => '15/01/2024', 'description' => '  POS   PAYMENT   RESTAURANT   ABC   ', 'amount' => 100, 'type' => 'expense'],
                ['date' => '16/01/2024', 'description' => 'ATM WITHDRAWAL KL SENTRAL 123456789', 'amount' => 200, 'type' => 'expense'],
                ['date' => '17/01/2024', 'description' => 'FPX PAYMENT TO SHOPEE MALAYSIA', 'amount' => 50, 'type' => 'expense'],
            ],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'][0]['description'])->toBe('PAYMENT RESTAURANT ABC')
            ->and($result['transactions'][1]['description'])->toBe('WITHDRAWAL KL SENTRAL')
            ->and($result['transactions'][2]['description'])->toBe('PAYMENT TO SHOPEE MALAYSIA');
    });
});

describe('UnifiedImportHandler Error Handling', function () {
    it('handles empty transactions array', function () {
        $mockAIResponse = [
            'transactions' => [],
        ];

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'])->toBeEmpty()
            ->and($result['metadata']['total_transactions'])->toBe(0)
            ->and($result['metadata']['date_range'])->toBeNull();
    });

    it('handles missing AI response data', function () {
        $mockAIResponse = []; // Empty response

        $this->aiService->shouldReceive('extractTransactions')
            ->once()
            ->andReturn($mockAIResponse);

        $result = $this->handler->processFile('content', 'pdf');

        expect($result['transactions'])->toBeEmpty()
            ->and($result['bank_name'])->toBe('Unknown Bank')
            ->and($result['account_number'])->toBe('')
            ->and($result['currency'])->toBe('RM');
    });
});
