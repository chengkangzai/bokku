<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UploadAttachmentTool extends Tool
{
    protected string $description = 'Upload a small attachment (under 500KB recommended) using base64-encoded content. Supports JPEG, PNG, GIF, WebP, PDF. For larger files, use request-upload-url-tool instead for better performance.';

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    private const MAX_FILES_PER_TRANSACTION = 5;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'file_content' => ['required', 'string'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', Rule::in(self::ALLOWED_MIME_TYPES)],
        ], [
            'transaction_id.required' => 'Transaction ID is required.',
            'file_content.required' => 'File content is required.',
            'file_name.required' => 'File name is required.',
            'mime_type.in' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP, PDF.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        $existingCount = $transaction->getMedia('receipts')->count();
        if ($existingCount >= self::MAX_FILES_PER_TRANSACTION) {
            return Response::error('Maximum of '.self::MAX_FILES_PER_TRANSACTION.' attachments per transaction reached.');
        }

        $decodedContent = base64_decode($validated['file_content'], true);
        if ($decodedContent === false) {
            return Response::error('Invalid base64-encoded file content.');
        }

        $size = strlen($decodedContent);
        if ($size > self::MAX_FILE_SIZE) {
            return Response::error('File size exceeds 5MB limit.');
        }

        $mimeType = $validated['mime_type'] ?? $this->detectMimeType($validated['file_name']);
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return Response::error('Invalid file type. Allowed: JPEG, PNG, GIF, WebP, PDF.');
        }

        $media = $transaction->addMediaFromString($decodedContent)
            ->usingFileName($validated['file_name'])
            ->toMediaCollection('receipts');

        return Response::structured([
            'message' => 'Attachment uploaded successfully.',
            'attachment' => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getTemporaryUrl(now()->addMinutes(5)),
                'thumb_url' => str_starts_with($media->mime_type, 'image/')
                    ? $media->getTemporaryUrl(now()->addMinutes(5), 'thumb')
                    : null,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The transaction ID to attach the file to')
                ->required(),
            'file_content' => $schema->string()
                ->description('Base64-encoded file content')
                ->required(),
            'file_name' => $schema->string()
                ->description('Original filename with extension (e.g., "receipt.pdf")')
                ->required(),
            'mime_type' => $schema->string()
                ->enum(self::ALLOWED_MIME_TYPES)
                ->description('MIME type of the file. Auto-detected from extension if not provided.'),
        ];
    }

    private function detectMimeType(string $fileName): ?string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => null,
        };
    }
}
