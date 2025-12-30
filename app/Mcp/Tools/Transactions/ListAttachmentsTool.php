<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[IsReadOnly]
class ListAttachmentsTool extends Tool
{
    protected string $description = 'List all attachments for a transaction.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
        ], [
            'transaction_id.required' => 'Transaction ID is required.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        $attachments = $transaction->getMedia('receipts')->map(fn (Media $media) => [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getTemporaryUrl(now()->addMinutes(5)),
            'thumb_url' => str_starts_with($media->mime_type, 'image/')
                ? $media->getTemporaryUrl(now()->addMinutes(5), 'thumb')
                : null,
            'created_at' => $media->created_at->toIso8601String(),
        ])->toArray();

        return Response::structured([
            'attachments' => $attachments,
            'count' => count($attachments),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()
                ->description('The transaction ID')
                ->required(),
        ];
    }
}
