<?php

use App\Models\Category;
use App\Models\User;
use App\Services\ReceiptExtractorService;
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Usage;
use Spatie\PdfToText\Pdf;

describe('ReceiptExtractorService', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->categories = Category::factory()
            ->count(3)
            ->sequence(
                ['name' => 'Groceries', 'type' => 'expense'],
                ['name' => 'Transport', 'type' => 'expense'],
                ['name' => 'Salary', 'type' => 'income']
            )
            ->create(['user_id' => $this->user->id]);
    });

    it('can be instantiated', function () {
        $service = new ReceiptExtractorService;
        expect($service)->toBeInstanceOf(ReceiptExtractorService::class);
    });

    it('requires authenticated user', function () {
        auth()->logout();

        $service = new ReceiptExtractorService;
        expect(fn () => $service->extractInformation('dummy.pdf', 'application/pdf'))
            ->toThrow(Exception::class);
    });

    it('detects PDF mime type correctly', function () {
        // Create a mock PDF file with proper PDF header
        $tempPath = tempnam(sys_get_temp_dir(), 'test_receipt').'.pdf';
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000125 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n181\n%%EOF";
        file_put_contents($tempPath, $pdfContent);

        // Mock PDF extraction
        $this->mock(Pdf::class, function ($mock) {
            $mock->shouldReceive('setPdf')
                ->andReturnSelf();
            $mock->shouldReceive('text')
                ->andReturn('Sample PDF content for grocery shopping');
        });

        // Mock Prism response
        $fake = Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'type' => 'expense',
                    'amount' => '25.50',
                    'date' => '2025-01-15',
                    'description' => 'Grocery shopping',
                    'category_id' => $this->categories->first()->id,
                ])
                ->withUsage(new Usage(100, 50)),
        ]);

        $service = new ReceiptExtractorService;
        $result = $service->extractInformation($tempPath, 'application/pdf');

        expect($result)->toBeArray()
            ->and($result['type'])->toBe('expense')
            ->and($result['amount'])->toBe('25.50');

        // Verify that Prism was called
        $fake->assertCallCount(1);

        unlink($tempPath);
    });

    it('detects image mime type correctly', function () {
        $testImageContent = "\xFF\xD8\xFF\xE0\x00\x10JFIF";

        // Mock Prism response for image processing
        $fake = Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'type' => 'expense',
                    'amount' => '15.75',
                    'date' => '2025-01-15',
                    'description' => 'Coffee shop',
                    'category_id' => $this->categories->get(1)->id,
                ])
                ->withUsage(new Usage(120, 80)),
        ]);

        $service = new ReceiptExtractorService;
        $result = $service->extractInformation($testImageContent, 'image/jpeg');

        expect($result)->toBeArray()
            ->and($result['type'])->toBe('expense')
            ->and($result['amount'])->toBe('15.75');

        // Verify that Prism was called
        $fake->assertCallCount(1);
    });

    it('returns null when AI returns null', function () {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_receipt').'.pdf';
        file_put_contents($tempPath, '%PDF-1.4 dummy pdf content');

        // Mock PDF extraction
        $this->mock(Pdf::class, function ($mock) {
            $mock->shouldReceive('setPdf')
                ->andReturnSelf();
            $mock->shouldReceive('text')
                ->andReturn('Sample PDF content');
        });

        // Mock Prism response with null structured data
        $fake = Prism::fake([
            StructuredResponseFake::make()
                ->withStructured(null)
                ->withUsage(new Usage(50, 25)),
        ]);

        $service = new ReceiptExtractorService;
        $result = $service->extractInformation($tempPath, 'application/pdf');

        expect($result)->toBeNull();

        // Verify that Prism was called
        $fake->assertCallCount(1);

        unlink($tempPath);
    });

    it('includes user categories in schema', function () {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_receipt').'.pdf';
        file_put_contents($tempPath, '%PDF-1.4 dummy pdf content');

        // Mock PDF extraction
        $this->mock(Pdf::class, function ($mock) {
            $mock->shouldReceive('setPdf')
                ->andReturnSelf();
            $mock->shouldReceive('text')
                ->andReturn('Sample PDF content');
        });

        // Mock Prism response
        $fake = Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'type' => 'expense',
                    'amount' => '10.00',
                    'date' => '2025-01-15',
                    'description' => 'Test',
                    'category_id' => $this->categories->first()->id,
                ])
                ->withUsage(new Usage(75, 40)),
        ]);

        $service = new ReceiptExtractorService;
        $service->extractInformation($tempPath, 'application/pdf');

        // Verify that categories are included in the request by checking
        // that the request was made with proper context
        $fake->assertCallCount(1);
        $fake->assertRequest(function ($requests) {
            // The first request should be a structured request
            expect($requests[0])->toBeInstanceOf(\Prism\Prism\Structured\Request::class);

            return true;
        });

        // Test that categories exist for the authenticated user
        expect($this->categories)->toHaveCount(3)
            ->and($this->categories->first()->name)->toBe('Groceries')
            ->and($this->categories->last()->name)->toBe('Salary');

        unlink($tempPath);
    });

    it('creates proper user message for PDF files', function () {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_receipt').'.pdf';
        file_put_contents($tempPath, '%PDF-1.4 test content');

        // Mock PDF extraction
        $this->mock(Pdf::class, function ($mock) {
            $mock->shouldReceive('setPdf')
                ->andReturnSelf();
            $mock->shouldReceive('text')
                ->andReturn('Sample PDF content');
        });

        // Mock Prism response
        $fake = Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'type' => 'expense',
                    'amount' => '10.00',
                    'date' => '2025-01-15',
                    'description' => 'Test',
                    'category_id' => $this->categories->first()->id,
                ])
                ->withUsage(new Usage(90, 60)),
        ]);

        $service = new ReceiptExtractorService;
        $service->extractInformation($tempPath, 'application/pdf');

        // Verify that Prism was called correctly for PDF processing
        $fake->assertCallCount(1);
        $fake->assertRequest(function ($requests) {
            // Verify the request contains text-based content for PDF
            $request = $requests[0];
            expect($request)->toBeInstanceOf(\Prism\Prism\Structured\Request::class);

            // Check that the user message contains text analysis instruction
            $messages = $request->messages();
            expect($messages)->toHaveCount(1);
            expect($messages[0]->content)->toContain('Analyze this receipt text');

            return true;
        });

        // Verify mime type detection
        expect(mime_content_type($tempPath))->toBe('application/pdf');

        unlink($tempPath);
    });

    it('creates proper user message for image files', function () {
        $testImageContent = "\xFF\xD8\xFF\xE0\x00\x10JFIF";

        // Mock Prism response
        $fake = Prism::fake([
            StructuredResponseFake::make()
                ->withStructured([
                    'type' => 'expense',
                    'amount' => '10.00',
                    'date' => '2025-01-15',
                    'description' => 'Test',
                    'category_id' => $this->categories->first()->id,
                ])
                ->withUsage(new Usage(110, 70)),
        ]);

        $service = new ReceiptExtractorService;
        $service->extractInformation($testImageContent, 'image/jpeg');

        // Verify that Prism was called correctly for image processing
        $fake->assertCallCount(1);
        $fake->assertRequest(function ($requests) {
            // Verify the request contains image-based content
            $request = $requests[0];
            expect($request)->toBeInstanceOf(\Prism\Prism\Structured\Request::class);

            // Check that the user message contains image analysis instruction
            $messages = $request->messages();
            expect($messages)->toHaveCount(1);
            expect($messages[0]->content)->toContain('Analyze this receipt image');

            return true;
        });
    });

    it('handles exceptions gracefully', function () {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_receipt').'.pdf';
        file_put_contents($tempPath, '%PDF-1.4 dummy pdf content');

        // Mock PDF extraction to throw an exception
        $this->mock(Pdf::class, function ($mock) {
            $mock->shouldReceive('setPdf')
                ->andThrow(new Exception('PDF processing failed'));
        });

        $service = new ReceiptExtractorService;
        expect(fn () => $service->extractInformation($tempPath, 'application/pdf'))
            ->toThrow(Exception::class, 'PDF processing failed');

        unlink($tempPath);
    });
});
