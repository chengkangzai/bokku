<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Services\AI\AIProviderService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UnifiedImportHandler
{
    protected AIProviderService $aiService;

    public function __construct(AIProviderService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Process any file type using AI
     */
    public function processFile(string $content, string $fileType, ?string $userInstructions = null): array
    {
        try {
            // Get user's existing categories
            $categories = [];
            if (Auth::check()) {
                $categories = Category::query()
                    ->where('user_id', Auth::id())
                    ->select('name', 'type')
                    ->get()
                    ->groupBy('type')
                    ->map(function ($group) {
                        return $group->pluck('name')->values()->all();
                    })
                    ->all();
            }

            // Extract transactions using AI
            $extracted = $this->aiService->extractTransactions(
                $content,
                $fileType,
                $userInstructions,
                $categories
            );

            // Format and validate the extracted data
            $transactions = $this->formatTransactions($extracted['transactions'] ?? []);
            $bankName = $this->detectBankName([], $content);

            return [
                'bank_name' => $bankName,
                'account_number' => null,
                'statement_period' => null,
                'currency' => 'RM',
                'transactions' => $transactions,
                'metadata' => [
                    'total_transactions' => count($transactions),
                    'date_range' => $this->getDateRange($transactions),
                    'file_type' => $fileType,
                    'processing_timestamp' => now()->toISOString(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Unified import handler failed', [
                'error' => $e->getMessage(),
                'file_type' => $fileType,
            ]);

            throw new Exception('Failed to process file: '.$e->getMessage());
        }
    }

    /**
     * Format and validate extracted transactions
     */
    protected function formatTransactions(array $transactions): array
    {
        $formatted = [];

        foreach ($transactions as $index => $transaction) {
            try {
                $formatted[] = $this->formatSingleTransaction($transaction, $index);
            } catch (Exception $e) {
                Log::warning('Failed to format transaction', [
                    'transaction' => $transaction,
                    'error' => $e->getMessage(),
                ]);

                // Skip invalid transactions instead of failing entire import
                continue;
            }
        }

        return $formatted;
    }

    /**
     * Format a single transaction
     */
    protected function formatSingleTransaction(array $transaction, int $index): array
    {
        // Parse and validate date
        $date = $this->parseDate($transaction['date'] ?? '');
        if (! $date) {
            throw new Exception("Invalid date for transaction {$index}");
        }

        // Parse amount
        $amount = $this->parseAmount($transaction['amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception("Invalid amount for transaction {$index}");
        }

        // Determine transaction type
        $type = $this->determineTransactionType($transaction);

        // Clean description
        $description = $this->cleanDescription($transaction['description'] ?? '');
        if (empty($description)) {
            throw new Exception("Empty description for transaction {$index}");
        }

        return [
            'date' => $date->format('Y-m-d'),
            'description' => $description,
            'amount' => $amount,
            'type' => $type,
            'reference' => $transaction['reference'] ?? null,
            'balance' => $transaction['balance'] ?? null,
            'category' => $transaction['category'] ?? null,
            'original_data' => $transaction,
        ];
    }

    /**
     * Parse date from various formats
     */
    protected function parseDate(string $dateStr): ?Carbon
    {
        if (empty($dateStr)) {
            return null;
        }

        // Try common Malaysian date formats first
        $formats = [
            'd/m/Y',    // 15/01/2024
            'd-m-Y',    // 15-01-2024
            'd/m/y',    // 15/01/24
            'd-m-y',    // 15-01-24
            'd M Y',    // 15 Jan 2024
            'Y-m-d',    // 2024-01-15 (ISO format)
            'm/d/Y',    // 01/15/2024 (US format)
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim($dateStr));
                if ($date && $date->year > 1900 && $date->year <= now()->year + 1) {
                    return $date;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        // Fallback to Carbon's parser
        try {
            $date = Carbon::parse($dateStr);
            if ($date && $date->year > 1900 && $date->year <= now()->year + 1) {
                return $date;
            }
        } catch (Exception $e) {
            // Log parsing failure
            Log::debug('Date parsing failed', [
                'date_string' => $dateStr,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Parse amount from various formats
     */
    protected function parseAmount($amount): float
    {
        if (is_numeric($amount)) {
            return abs(floatval($amount));
        }

        if (is_string($amount)) {
            // Remove currency symbols, commas, and spaces
            $cleaned = preg_replace('/[^\d.-]/', '', $amount);

            return abs(floatval($cleaned));
        }

        return 0.0;
    }

    /**
     * Determine transaction type from data
     */
    protected function determineTransactionType(array $transaction): string
    {
        // Use explicit type if provided
        if (isset($transaction['type'])) {
            $type = strtolower($transaction['type']);
            if (in_array($type, ['income', 'expense', 'transfer'])) {
                return $type;
            }
        }

        // Determine from amount sign or keywords
        $amount = $transaction['amount'] ?? 0;
        $description = strtolower($transaction['description'] ?? '');

        // Check for transfer keywords
        if (Str::contains($description, ['transfer', 'tfr', 'ibg', 'instant transfer', 'fund transfer'])) {
            return 'transfer';
        }

        // Check for income keywords
        if (Str::contains($description, ['salary', 'bonus', 'dividend', 'interest', 'refund', 'cashback'])) {
            return 'income';
        }

        // Default based on amount if numeric
        if (is_numeric($amount)) {
            return floatval($amount) >= 0 ? 'income' : 'expense';
        }

        // Default to expense
        return 'expense';
    }

    /**
     * Clean transaction description
     */
    protected function cleanDescription(string $description): string
    {
        // Remove excessive whitespace
        $cleaned = preg_replace('/\s+/', ' ', trim($description));

        // Remove common bank prefixes/codes that add no value
        $patterns = [
            '/^(POS|ATM|IBG|GIRO|FPX|JOM PAY|DUIT NOW)\s*/i',
            '/\s+\d{6,}$/',  // Remove trailing reference numbers
        ];

        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, ' ', $cleaned);
        }

        return trim($cleaned);
    }

    /**
     * Detect bank name from extracted data or content
     */
    protected function detectBankName(array $extracted, string $content): string
    {
        // Use AI-detected bank name if available
        if (! empty($extracted['bank_name'])) {
            return $extracted['bank_name'];
        }

        // Try to detect from content patterns
        $contentLower = strtolower($content);
        $bankPatterns = [
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
        ];

        foreach ($bankPatterns as $key => $bankName) {
            if (Str::contains($contentLower, [$key, strtolower($bankName)])) {
                return $bankName;
            }
        }

        return 'Unknown Bank';
    }

    /**
     * Get date range from transactions
     */
    protected function getDateRange(array $transactions): ?string
    {
        if (empty($transactions)) {
            return null;
        }

        $dates = array_column($transactions, 'date');
        if (empty($dates)) {
            return null;
        }

        sort($dates);
        $startDate = Carbon::parse($dates[0])->format('d M Y');
        $endDate = Carbon::parse($dates[array_key_last($dates)])->format('d M Y');

        return $startDate === $endDate ? $startDate : "{$startDate} - {$endDate}";
    }

    /**
     * Validate extracted transactions
     */
    public function validate(array $transactions): array
    {
        $errors = [];

        foreach ($transactions as $index => $transaction) {
            $rowErrors = [];

            // Validate required fields
            if (empty($transaction['date'])) {
                $rowErrors[] = 'Date is required';
            }
            if (empty($transaction['description'])) {
                $rowErrors[] = 'Description is required';
            }
            if (! isset($transaction['amount']) || $transaction['amount'] <= 0) {
                $rowErrors[] = 'Valid amount is required';
            }
            if (! in_array($transaction['type'] ?? '', ['income', 'expense', 'transfer'])) {
                $rowErrors[] = 'Valid transaction type is required';
            }

            // Validate date format
            if (! empty($transaction['date'])) {
                try {
                    Carbon::parse($transaction['date']);
                } catch (Exception $e) {
                    $rowErrors[] = 'Invalid date format';
                }
            }

            if (! empty($rowErrors)) {
                $errors[$index] = $rowErrors;
            }
        }

        return $errors;
    }
}
