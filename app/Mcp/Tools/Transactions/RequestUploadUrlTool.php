<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\PendingUpload;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class RequestUploadUrlTool extends Tool
{
    protected string $description = 'Request a presigned URL for uploading an attachment directly to storage. Returns a URL for direct upload without base64 encoding. For files larger than 500KB, this is more efficient than upload-attachment-tool.';

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    private const MAX_FILE_SIZE = 12 * 1024 * 1024; // 12MB

    private const MAX_FILES_PER_TRANSACTION = 5;

    private const URL_EXPIRY_MINUTES = 15;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_FILE_SIZE],
            'mime_type' => ['required', 'string', Rule::in(self::ALLOWED_MIME_TYPES)],
        ], [
            'transaction_id.required' => 'Transaction ID is required.',
            'file_name.required' => 'File name is required.',
            'file_size.required' => 'File size is required.',
            'file_size.max' => 'File size exceeds 12MB limit.',
            'mime_type.required' => 'MIME type is required.',
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

        $uploadToken = Str::random(64);
        $extension = pathinfo($validated['file_name'], PATHINFO_EXTENSION);
        $storageKey = "pending-uploads/{$request->user()->id}/{$uploadToken}.{$extension}";

        ['url' => $presignedUrl, 'headers' => $headers] = $this->generatePresignedUrl($storageKey, $validated['mime_type']);

        $pendingUpload = PendingUpload::create([
            'user_id' => $request->user()->id,
            'transaction_id' => $validated['transaction_id'],
            'upload_token' => $uploadToken,
            'storage_key' => $storageKey,
            'original_filename' => $validated['file_name'],
            'mime_type' => $validated['mime_type'],
            'expected_size' => $validated['file_size'],
            'expires_at' => now()->addMinutes(self::URL_EXPIRY_MINUTES),
        ]);

        return Response::structured([
            'upload_url' => $presignedUrl,
            'upload_token' => $uploadToken,
            'method' => 'PUT',
            'headers' => $headers,
            'expires_at' => $pendingUpload->expires_at->toIso8601String(),
            'instructions' => 'Upload file using PUT request to upload_url with the specified Content-Type header. Then call confirm-upload-tool with the upload_token to attach the file to the transaction.',
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
            'file_name' => $schema->string()
                ->description('Original filename with extension (e.g., "receipt.pdf")')
                ->required(),
            'file_size' => $schema->integer()
                ->description('File size in bytes (max 5MB = 5242880 bytes)')
                ->required(),
            'mime_type' => $schema->string()
                ->enum(self::ALLOWED_MIME_TYPES)
                ->description('MIME type of the file')
                ->required(),
        ];
    }

    private function generatePresignedUrl(string $storageKey, string $mimeType): array
    {
        return Storage::disk('s3')->temporaryUploadUrl(
            $storageKey,
            now()->addMinutes(self::URL_EXPIRY_MINUTES),
            ['ContentType' => $mimeType]
        );
    }
}
