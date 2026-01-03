<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Transactions\DeleteAttachmentTool;
use App\Mcp\Tools\Transactions\ListAttachmentsTool;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Minimal valid 1x1 PNG image (base64)
const VALID_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

// Minimal valid PDF
const VALID_PDF_BASE64 = 'JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKL01lZGlhQm94IFswIDAgNjEyIDc5Ml0KPj4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgo+PgplbmRvYmoKeHJlZgowIDQKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwMDA5IDAwMDAwIG4gCjAwMDAwMDAwNTggMDAwMDAgbiAKMDAwMDAwMDE0NSAwMDAwMCBuIAp0cmFpbGVyCjw8Ci9TaXplIDQKL1Jvb3QgMSAwIFIKPj4Kc3RhcnR4cmVmCjE5NwolJUVPRgo=';

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'balance' => 1000.00,
        'initial_balance' => 1000.00,
    ]);
    $this->transaction = Transaction::factory()->expense()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
    ]);
});

describe('ListAttachmentsTool', function () {
    it('returns empty array when no attachments', function () {
        $response = BokkuServer::actingAs($this->user)->tool(ListAttachmentsTool::class, [
            'transaction_id' => $this->transaction->id,
        ]);

        $response->assertOk()
            ->assertSee('attachments')
            ->assertSee('"count": 0');
    });

    it('returns attachments for transaction', function () {
        $this->transaction->addMediaFromString(base64_decode(VALID_PNG_BASE64))
            ->usingFileName('receipt.png')
            ->toMediaCollection('receipts');

        $response = BokkuServer::actingAs($this->user)->tool(ListAttachmentsTool::class, [
            'transaction_id' => $this->transaction->id,
        ]);

        $response->assertOk()
            ->assertSee('receipt.png')
            ->assertSee('"count": 1');
    });

    it('returns error for non-existent transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(ListAttachmentsTool::class, [
            'transaction_id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $otherTransaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ListAttachmentsTool::class, [
            'transaction_id' => $otherTransaction->id,
        ]);

        $response->assertHasErrors();
    });
});

describe('DeleteAttachmentTool', function () {
    it('deletes attachment from transaction', function () {
        $media = $this->transaction->addMediaFromString(base64_decode(VALID_PNG_BASE64))
            ->usingFileName('receipt.png')
            ->toMediaCollection('receipts');

        $response = BokkuServer::actingAs($this->user)->tool(DeleteAttachmentTool::class, [
            'transaction_id' => $this->transaction->id,
            'attachment_id' => $media->id,
        ]);

        $response->assertOk()
            ->assertSee('deleted successfully');

        expect($this->transaction->getMedia('receipts')->count())->toBe(0);
    });

    it('returns error for non-existent transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeleteAttachmentTool::class, [
            'transaction_id' => 99999,
            'attachment_id' => 1,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for non-existent attachment', function () {
        $response = BokkuServer::actingAs($this->user)->tool(DeleteAttachmentTool::class, [
            'transaction_id' => $this->transaction->id,
            'attachment_id' => 99999,
        ]);

        $response->assertHasErrors();
    });

    it('cannot delete attachment from other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $otherTransaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
        ]);

        $media = $otherTransaction->addMediaFromString(base64_decode(VALID_PNG_BASE64))
            ->usingFileName('receipt.png')
            ->toMediaCollection('receipts');

        $response = BokkuServer::actingAs($this->user)->tool(DeleteAttachmentTool::class, [
            'transaction_id' => $otherTransaction->id,
            'attachment_id' => $media->id,
        ]);

        $response->assertHasErrors();

        expect($otherTransaction->getMedia('receipts')->count())->toBe(1);
    });

    it('cannot delete attachment that belongs to different transaction', function () {
        $otherTransaction = Transaction::factory()->expense()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $media = $otherTransaction->addMediaFromString(base64_decode(VALID_PNG_BASE64))
            ->usingFileName('receipt.png')
            ->toMediaCollection('receipts');

        $response = BokkuServer::actingAs($this->user)->tool(DeleteAttachmentTool::class, [
            'transaction_id' => $this->transaction->id,
            'attachment_id' => $media->id,
        ]);

        $response->assertHasErrors();
    });
});
