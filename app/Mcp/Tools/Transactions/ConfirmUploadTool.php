<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\PendingUpload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ConfirmUploadTool extends Tool
{
    protected string $description = 'Confirm a completed file upload and attach it to the transaction. Use this after uploading a file using the presigned URL from request-upload-url-tool.';

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'upload_token' => ['required', 'string', 'size:64'],
        ], [
            'upload_token.required' => 'Upload token is required.',
            'upload_token.size' => 'Invalid upload token format.',
        ]);

        $pendingUpload = PendingUpload::where('upload_token', $validated['upload_token'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $pendingUpload) {
            return Response::error('Upload token not found or access denied.');
        }

        if ($pendingUpload->isExpired()) {
            $this->cleanupExpiredUpload($pendingUpload);

            return Response::error('Upload token has expired. Please request a new upload URL.');
        }

        $disk = Storage::disk('s3');

        if (! $disk->exists($pendingUpload->storage_key)) {
            return Response::error('File not found in storage. Please upload the file first using the presigned URL.');
        }

        $actualSize = $disk->size($pendingUpload->storage_key);
        if ($actualSize > self::MAX_FILE_SIZE) {
            $this->cleanupUpload($pendingUpload);

            return Response::error('Uploaded file exceeds 5MB limit.');
        }

        $transaction = $pendingUpload->transaction;

        $media = $transaction->addMediaFromDisk($pendingUpload->storage_key, 's3')
            ->usingFileName($pendingUpload->original_filename)
            ->toMediaCollection('receipts');

        $pendingUpload->delete();

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
            'upload_token' => $schema->string()
                ->description('The upload token received from request-upload-url-tool')
                ->required(),
        ];
    }

    private function cleanupExpiredUpload(PendingUpload $pendingUpload): void
    {
        $disk = Storage::disk('s3');

        if ($disk->exists($pendingUpload->storage_key)) {
            $disk->delete($pendingUpload->storage_key);
        }

        $pendingUpload->delete();
    }

    private function cleanupUpload(PendingUpload $pendingUpload): void
    {
        $disk = Storage::disk('s3');
        $disk->delete($pendingUpload->storage_key);
        $pendingUpload->delete();
    }
}
