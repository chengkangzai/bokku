<?php

use App\Mcp\Servers\BokkuServer;
use App\Mcp\Tools\Transactions\ConfirmUploadTool;
use App\Mcp\Tools\Transactions\RequestUploadUrlTool;
use App\Models\Account;
use App\Models\PendingUpload;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesS3Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);
uses(FakesS3Storage::class);

// Minimal valid 1x1 PNG image (base64)
const VALID_PNG_BASE64_PRESIGNED = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

beforeEach(function () {
    $this->fakeS3();

    // Store valid PNG content for use in tests
    $this->validPngContent = base64_decode(VALID_PNG_BASE64_PRESIGNED);

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

describe('RequestUploadUrlTool', function () {
    it('returns presigned URL for valid request', function () {
        $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
            'transaction_id' => $this->transaction->id,
            'file_name' => 'receipt.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
        ]);

        $response->assertOk()
            ->assertSee('upload_url')
            ->assertSee('upload_token')
            ->assertSee('method')
            ->assertSee('PUT');

        expect(PendingUpload::count())->toBe(1);

        $pendingUpload = PendingUpload::first();
        expect($pendingUpload->user_id)->toBe($this->user->id)
            ->and($pendingUpload->transaction_id)->toBe($this->transaction->id)
            ->and($pendingUpload->original_filename)->toBe('receipt.png')
            ->and($pendingUpload->mime_type)->toBe('image/png');
    });

    it('returns error for non-existent transaction', function () {
        $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
            'transaction_id' => 99999,
            'file_name' => 'receipt.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users transaction', function () {
        $otherAccount = Account::factory()->create(['user_id' => $this->otherUser->id]);
        $otherTransaction = Transaction::factory()->create([
            'user_id' => $this->otherUser->id,
            'account_id' => $otherAccount->id,
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
            'transaction_id' => $otherTransaction->id,
            'file_name' => 'receipt.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
        ]);

        $response->assertHasErrors();
    });

    it('rejects file size exceeding 5MB', function () {
        $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
            'transaction_id' => $this->transaction->id,
            'file_name' => 'receipt.png',
            'file_size' => 6 * 1024 * 1024, // 6MB
            'mime_type' => 'image/png',
        ]);

        $response->assertHasErrors();
    });

    it('rejects unsupported file types', function () {
        $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
            'transaction_id' => $this->transaction->id,
            'file_name' => 'document.doc',
            'file_size' => 1024,
            'mime_type' => 'application/msword',
        ]);

        $response->assertHasErrors();
    });

    it('enforces maximum 5 attachments per transaction', function () {
        // Add 5 attachments with valid PNG content
        for ($i = 0; $i < 5; $i++) {
            $this->transaction->addMediaFromString($this->validPngContent)
                ->usingFileName("receipt{$i}.png")
                ->toMediaCollection('receipts');
        }

        $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
            'transaction_id' => $this->transaction->id,
            'file_name' => 'receipt6.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
        ]);

        $response->assertHasErrors();
    });

    it('accepts all supported mime types', function () {
        $mimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
        ];

        foreach ($mimeTypes as $index => $mimeType) {
            $response = BokkuServer::actingAs($this->user)->tool(RequestUploadUrlTool::class, [
                'transaction_id' => $this->transaction->id,
                'file_name' => "file{$index}.test",
                'file_size' => 1024,
                'mime_type' => $mimeType,
            ]);

            $response->assertOk();
        }
    });
});

describe('ConfirmUploadTool', function () {
    it('confirms upload and converts PNG to WebP', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('a', 64),
            'storage_key' => 'pending-uploads/1/test.png',
            'original_filename' => 'receipt.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->addMinutes(15),
        ]);

        Storage::disk('s3')->put($pendingUpload->storage_key, $this->validPngContent);

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertOk()
            ->assertSee('Attachment uploaded successfully')
            ->assertSee('receipt.webp');

        expect($this->transaction->getMedia('receipts')->count())->toBe(1)
            ->and(PendingUpload::count())->toBe(0);

        $media = $this->transaction->getMedia('receipts')->first();
        expect($media->file_name)->toBe('receipt.webp');
    });

    it('does not convert WebP files', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('w', 64),
            'storage_key' => 'pending-uploads/1/test.webp',
            'original_filename' => 'already-webp.webp',
            'mime_type' => 'image/webp',
            'expected_size' => 1024,
            'expires_at' => now()->addMinutes(15),
        ]);

        Storage::disk('s3')->put($pendingUpload->storage_key, $this->validPngContent);

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertOk()
            ->assertSee('already-webp.webp');

        $media = $this->transaction->getMedia('receipts')->first();
        expect($media->file_name)->toBe('already-webp.webp');
    });

    it('does not convert PDF files', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('p', 64),
            'storage_key' => 'pending-uploads/1/test.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'expected_size' => 1024,
            'expires_at' => now()->addMinutes(15),
        ]);

        Storage::disk('s3')->put($pendingUpload->storage_key, '%PDF-1.4 fake pdf content');

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertOk()
            ->assertSee('document.pdf');

        $media = $this->transaction->getMedia('receipts')->first();
        expect($media->file_name)->toBe('document.pdf');
    });

    it('returns error for non-existent token', function () {
        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => str_repeat('x', 64),
        ]);

        $response->assertHasErrors();
    });

    it('returns error for expired token', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('b', 64),
            'storage_key' => 'pending-uploads/1/test.png',
            'original_filename' => 'receipt.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->subMinutes(5), // Expired
        ]);

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertHasErrors();
        expect(PendingUpload::count())->toBe(0);
    });

    it('returns error when file not found in storage', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('c', 64),
            'storage_key' => 'pending-uploads/1/missing.png',
            'original_filename' => 'receipt.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Don't upload the file

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertHasErrors();
    });

    it('returns error for other users token', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->otherUser->id, // Other user
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('d', 64),
            'storage_key' => 'pending-uploads/2/test.png',
            'original_filename' => 'receipt.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->addMinutes(15),
        ]);

        Storage::disk('s3')->put($pendingUpload->storage_key, $this->validPngContent);

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertHasErrors();
    });

    it('rejects file exceeding 5MB', function () {
        $pendingUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('e', 64),
            'storage_key' => 'pending-uploads/1/large.png',
            'original_filename' => 'large.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Upload a file larger than 5MB
        Storage::disk('s3')->put($pendingUpload->storage_key, str_repeat('x', 6 * 1024 * 1024));

        $response = BokkuServer::actingAs($this->user)->tool(ConfirmUploadTool::class, [
            'upload_token' => $pendingUpload->upload_token,
        ]);

        $response->assertHasErrors();
        expect(PendingUpload::count())->toBe(0)
            ->and(Storage::disk('s3')->exists($pendingUpload->storage_key))->toBeFalse();
    });
});

describe('CleanupPendingUploadsCommand', function () {
    it('removes expired pending uploads', function () {
        $expiredUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('f', 64),
            'storage_key' => 'pending-uploads/1/expired.png',
            'original_filename' => 'expired.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->subHour(),
        ]);

        Storage::disk('s3')->put($expiredUpload->storage_key, $this->validPngContent);

        $validUpload = PendingUpload::create([
            'user_id' => $this->user->id,
            'transaction_id' => $this->transaction->id,
            'upload_token' => str_repeat('g', 64),
            'storage_key' => 'pending-uploads/1/valid.png',
            'original_filename' => 'valid.png',
            'mime_type' => 'image/png',
            'expected_size' => 1024,
            'expires_at' => now()->addHour(),
        ]);

        Storage::disk('s3')->put($validUpload->storage_key, $this->validPngContent);

        $this->artisan('uploads:cleanup-pending')
            ->assertSuccessful();

        expect(PendingUpload::count())->toBe(1)
            ->and(PendingUpload::first()->upload_token)->toBe($validUpload->upload_token)
            ->and(Storage::disk('s3')->exists($expiredUpload->storage_key))->toBeFalse()
            ->and(Storage::disk('s3')->exists($validUpload->storage_key))->toBeTrue();
    });

    it('handles no expired uploads gracefully', function () {
        $this->artisan('uploads:cleanup-pending')
            ->assertSuccessful()
            ->expectsOutput('No expired pending uploads found.');
    });
});
