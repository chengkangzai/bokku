<?php

namespace Tests\Support;

use Illuminate\Filesystem\LocalFilesystemAdapter;

class FakeS3Adapter extends LocalFilesystemAdapter
{
    /**
     * Get a temporary upload URL for the file at the given path.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     */
    public function temporaryUploadUrl($path, $expiration, array $options = []): array
    {
        return [
            'url' => 'https://fake-bucket.s3.amazonaws.com/'.$path.'?presigned=true&expires='.$expiration->getTimestamp(),
            'headers' => [
                'Content-Type' => $options['ContentType'] ?? 'application/octet-stream',
            ],
        ];
    }
}
