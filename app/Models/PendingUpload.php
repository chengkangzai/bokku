<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingUpload extends Model
{
    protected $fillable = [
        'user_id',
        'transaction_id',
        'upload_token',
        'storage_key',
        'original_filename',
        'mime_type',
        'expected_size',
        'expires_at',
    ];

    protected $casts = [
        'expected_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
