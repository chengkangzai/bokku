<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteAttachmentTool extends Tool
{
    protected string $description = 'Delete an attachment from a transaction.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'attachment_id' => ['required', 'integer'],
        ], [
            'transaction_id.required' => 'Transaction ID is required.',
            'attachment_id.required' => 'Attachment ID is required.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->find($validated['transaction_id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        $media = $transaction->getMedia('receipts')
            ->firstWhere('id', $validated['attachment_id']);

        if (! $media) {
            return Response::error('Attachment not found.');
        }

        $fileName = $media->file_name;
        $media->delete();

        return Response::structured([
            'message' => "Attachment '{$fileName}' deleted successfully.",
            'deleted' => true,
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
            'attachment_id' => $schema->integer()
                ->description('The attachment ID to delete')
                ->required(),
        ];
    }
}
