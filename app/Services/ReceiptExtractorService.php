<?php

namespace App\Services;

use App\Models\Category;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Spatie\PdfToText\Pdf;

class ReceiptExtractorService
{
    /**
     * @return array{
     *     type: string,
     *     amount: string,
     *     date: string,
     *     description: string,
     *     category_id: string
     * }|null
     *
     * @throws \Spatie\PdfToText\Exceptions\PdfNotFound
     */
    public function extractInformation(string $input, string $mimeType): ?array
    {
        $categories = Category::query()
            ->where('user_id', auth()->user()->id)
            ->select('id', 'name', 'type')
            ->get()
            ->toJson();

        $schema = new ObjectSchema(
            name: 'extract_receipt_information',
            description: 'Extract structured information from a receipt or document based on custom form fields',
            properties: [
                new StringSchema('type', 'Extract transaction type. Must be one of the \'income\', \'expense\', \'transfer\' '),
                new NumberSchema('amount', 'Extract total amount from the receipt. Convert any currency symbols or text numbers. Return 0 if not found or unclear.'),
                new StringSchema('date', 'Extract date. Look for dates in formats like DD/MM/YYYY, MM/DD/YYYY, DD-MM-YYYY, etc. Convert to YYYY-MM-DD format. Check near labels like \'Date:\', \'Issued:\', \'Transaction:\', etc. Return \'N/A\' if no valid date found.'),
                new StringSchema('description', 'Extract transaction description. Short and concise summary of the spending.'),
                new StringSchema('category_id', 'Examine what category best matches this transaction. It should match the transaction type. Return the ID (not name) of the matching category from the options below: '.$categories),
            ],
            requiredFields: ['amount'],
        );

        // Create appropriate content based on mime type
        if ($mimeType === 'application/pdf') {
            // For PDFs: $input is file path, extract text first
            $pdfText = (new Pdf)
                ->setPdf($input)
                ->text();

            $userPromptContent = new UserMessage(
                'Analyze this receipt text and extract the following fields. Please extract the requested information.<content>'.$pdfText.'</content>'
            );
        } elseif (str_starts_with($mimeType, 'image/')) {
            // For images: $input is raw content, use it directly for vision analysis
            $userPromptContent = new UserMessage(
                'Analyze this receipt image and extract the following fields. Please extract the requested information from the receipt.',
                [Image::fromRawContent($input, $mimeType)]
            );
        } else {
            throw new \Exception('Unsupported file type: '.$mimeType);
        }

        $response = Prism::structured()
            ->withSchema($schema)
            ->using(Provider::OpenAI, 'gpt-5-nano-2025-08-07')
            ->withSystemPrompt(view('prompts.receipt-extractor'))
            ->withMessages([$userPromptContent])
            ->whenProvider(Provider::OpenAI, fn ($request) => $request->withProviderOptions([
                'schema' => [
                    'strict' => true,
                ],
            ]))
            ->withMaxTokens(3000)
            ->asStructured();

        return $response->structured;
    }
}
