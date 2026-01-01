<?php

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;

trait FakesS3Storage
{
    /**
     * Replace the S3 disk with a fake that supports temporaryUploadUrl.
     */
    protected function fakeS3(): FakeS3Adapter
    {
        $root = storage_path('framework/testing/disks/s3');

        (new Filesystem)->cleanDirectory($root);

        $localAdapter = new LocalAdapter($root);
        $flysystem = new Flysystem($localAdapter);

        $disk = new FakeS3Adapter($flysystem, $localAdapter, ['root' => $root]);

        // Set up temporary URL callback for downloads (like Storage::fake() does)
        $disk->buildTemporaryUrlsUsing(function ($path, $expiration) {
            return URL::to($path.'?expiration='.$expiration->getTimestamp());
        });

        Storage::set('s3', $disk);

        return $disk;
    }
}
