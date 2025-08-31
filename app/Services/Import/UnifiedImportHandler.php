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
    public function __construct(
        protected AIProviderService $aiService
    ) {}

    private const BANK_PATTERNS = [
        'maybank' => 'Maybank',
        'cimb' => 'CIMB Bank',
        'public' => 'Public Bank',
        'rhb' => 'RHB Bank',
        'hong leong' => 'Hong Leong Bank',
        'ambank' => 'AmBank',
        'bank islam' => 'Bank Islam',
        'ocbc' => 'OCBC Bank',
        'hsbc' => 'HSBC',
        'standard chartered' => 'Standard Chartered',
    ];

    private const DATE_FORMATS = [
        'd/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y',
        'd M Y', 'Y-m-d', 'm/d/Y',
    ];

    public function processFile(string $content, string $fileType, ?string $userInstructions = null): array
    {
        try {
            $categories = $this->getUserCategories();
            $extracted = $this->aiService->extractTransactions($content, $fileType, $userInstructions, $categories);
            $transactions = $this->formatTransactions($extracted['transactions'] ?? []);

            return [
                'bank_name' => $this->detectBankName($content),
                'account_number' => null,
                'statement_period' => null,
                'currency' => 'MYR',
                'transactions' => $transactions,
                'metadata' => [
                    'total_transactions' => count($transactions),
                    'date_range' => $this->getDateRange($transactions),
                    'file_type' => $fileType,
                    'processing_timestamp' => now()->toISOString(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Import processing failed', ['error' => $e->getMessage(), 'file_type' => $fileType]);
            throw new Exception('Failed to process file: '.$e->getMessage());
        }
    }

    private function getUserCategories(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return Category::where('user_id', Auth::id())
            ->select('name', 'type')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->pluck('name')->values()->all())
            ->all();
    }

    private function formatTransactions(array $transactions): array
    {
        $formatted = [];

        foreach ($transactions as $index => $transaction) {
            try {
                $formatted[] = $this->formatTransaction($transaction, $index);
            } catch (Exception $e) {
                Log::warning('Skipping invalid transaction', ['index' => $index, 'error' => $e->getMessage()]);
            }
        }

        return $formatted;
    }

    private function formatTransaction(array $transaction, int $index): array
    {
        $date = $this->parseDate($transaction['date'] ?? '');
        $amount = $this->parseAmount($transaction['amount'] ?? 0);
        $description = $this->cleanDescription($transaction['description'] ?? '');

        if (! $date) {
            throw new Exception("Invalid date for transaction {$index}");
        }
        if ($amount <= 0) {
            throw new Exception("Invalid amount for transaction {$index}");
        }
        if (empty($description)) {
            throw new Exception("Empty description for transaction {$index}");
        }

        return [
            'date' => $date->format('Y-m-d'),
            'description' => $description,
            'amount' => $amount,
            'type' => $this->determineTransactionType($transaction),
            'reference' => $transaction['reference'] ?? null,
            'balance' => $transaction['balance'] ?? null,
            'category' => $transaction['category'] ?? null,
            'original_data' => $transaction,
        ];
    }

    private function parseDate(string $dateStr): ?Carbon
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);

        // Try specific formats first, then fallback to Carbon's auto-parsing
        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateStr);
                if ($this->isValidYear($date)) {
                    return $date;
                }
            } catch (Exception) {
                continue;
            }
        }

        // Fallback to Carbon's intelligent parsing
        try {
            $date = Carbon::parse($dateStr);

            return $this->isValidYear($date) ? $date : null;
        } catch (Exception) {
            return null;
        }
    }

    private function isValidYear(Carbon $date): bool
    {
        return $date->year > 1900 && $date->year <= now()->year + 1;
    }

    private function parseAmount($amount): float
    {
        if (is_numeric($amount)) {
            return abs((float) $amount);
        }

        if (is_string($amount)) {
            $cleaned = preg_replace('/[^\d.-]/', '', $amount);

            return abs((float) $cleaned);
        }

        return 0.0;
    }

    private function determineTransactionType(array $transaction): string
    {
        // Use explicit type if provided and valid
        $type = strtolower($transaction['type'] ?? '');
        if (in_array($type, ['income', 'expense', 'transfer'])) {
            return $type;
        }

        $description = strtolower($transaction['description'] ?? '');

        // Check keywords for specific types
        if (Str::contains($description, ['transfer', 'tfr', 'ibg', 'instant transfer', 'fund transfer'])) {
            return 'transfer';
        }

        if (Str::contains($description, ['salary', 'bonus', 'dividend', 'interest', 'refund', 'cashback'])) {
            return 'income';
        }

        // Default based on amount sign, or expense as fallback
        $amount = $transaction['amount'] ?? 0;

        return is_numeric($amount) && (float) $amount >= 0 ? 'income' : 'expense';
    }

    private function cleanDescription(string $description): string
    {
        $cleaned = preg_replace('/\s+/', ' ', trim($description));

        // Remove bank prefixes and trailing reference numbers
        $patterns = [
            '/^(POS|ATM|IBG|GIRO|FPX|JOM PAY|DUIT NOW)\s*/i',
            '/\s+\d{6,}$/',
        ];

        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, ' ', $cleaned);
        }

        return trim($cleaned);
    }

    private function detectBankName(string $content): string
    {
        $contentLower = strtolower($content);

        foreach (self::BANK_PATTERNS as $pattern => $bankName) {
            if (str_contains($contentLower, $pattern)) {
                return $bankName;
            }
        }

        return 'Unknown Bank';
    }

    private function getDateRange(array $transactions): ?string
    {
        $dates = array_column($transactions, 'date');
        if (empty($dates)) {
            return null;
        }

        sort($dates);
        $startDate = Carbon::parse($dates[0])->format('d M Y');
        $endDate = Carbon::parse($dates[array_key_last($dates)])->format('d M Y');

        return $startDate === $endDate ? $startDate : "{$startDate} - {$endDate}";
    }
}
