<?php

use App\Models\Transaction;

describe('Media Name Sanitization', function () {
    it('sanitizes non-ASCII bytes from media name on save', function () {
        $transaction = Transaction::factory()->create();

        $media = $transaction
            ->addMediaFromString('fake-image-content')
            ->usingFileName('test.webp')
            ->usingName("\x96+\xDE\xC2*\xDE")
            ->toMediaCollection();

        expect($media->name)->not->toContain("\x96")
            ->and($media->name)->not->toContain("\xDE")
            ->and($media->name)->toMatch('/^[\x20-\x7E]+$/');
    });

    it('preserves valid ASCII media names', function () {
        $transaction = Transaction::factory()->create();

        $media = $transaction
            ->addMediaFromString('fake-image-content')
            ->usingFileName('test.webp')
            ->usingName('WhatsApp Image 2026-03-24 at 00.17.30')
            ->toMediaCollection();

        expect($media->name)->toBe('WhatsApp Image 2026-03-24 at 00.17.30');
    });

    it('generates a ULID name when all characters are non-ASCII', function () {
        $transaction = Transaction::factory()->create();

        $media = $transaction
            ->addMediaFromString('fake-image-content')
            ->usingFileName('test.webp')
            ->usingName("\x96\xDE\xC2\xDE")
            ->toMediaCollection();

        expect($media->name)->toMatch('/^[\x20-\x7E]+$/')
            ->and(strlen($media->name))->toBeGreaterThan(0);
    });

    it('sanitizes Chinese characters from media name', function () {
        $transaction = Transaction::factory()->create();

        $media = $transaction
            ->addMediaFromString('fake-image-content')
            ->usingFileName('test.webp')
            ->usingName('把爱带回家')
            ->toMediaCollection();

        expect($media->name)->toMatch('/^[\x20-\x7E]+$/')
            ->and(strlen($media->name))->toBeGreaterThan(0);
    });
});
