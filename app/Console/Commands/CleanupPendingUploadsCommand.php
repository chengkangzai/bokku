<?php

namespace App\Console\Commands;

use App\Models\PendingUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupPendingUploadsCommand extends Command
{
    protected $signature = 'uploads:cleanup-pending';

    protected $description = 'Clean up expired pending uploads and orphaned storage files';

    public function handle(): int
    {
        $expired = PendingUpload::where('expires_at', '<', now())->get();

        if ($expired->isEmpty()) {
            $this->info('No expired pending uploads found.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('s3');
        $cleanedCount = 0;

        foreach ($expired as $upload) {
            $this->line("Cleaning up: {$upload->storage_key}");

            if ($disk->exists($upload->storage_key)) {
                $disk->delete($upload->storage_key);
            }

            $upload->delete();
            $cleanedCount++;
        }

        $this->info("Cleaned up {$cleanedCount} expired pending uploads.");

        return self::SUCCESS;
    }
}
