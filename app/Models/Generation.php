<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    public const PENDING = 'pending';
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';

    // status can only move forward through these, never back
    private const RANK = [
        self::PENDING => 0,
        self::QUEUED => 1,
        self::PROCESSING => 2,
        self::SUCCEEDED => 3,
        self::FAILED => 3,
    ];

    protected $fillable = [
        'user_id', 'idempotency_key', 'pixelforge_id', 'prompt', 'size', 'quality',
        'reference_images', 'status', 'rates', 'usage', 'estimated_charge_nanos',
        'provider_cost_nanos', 'charge_nanos', 'image_url', 'image_url_expires_at',
        'error_code', 'error_message', 'submit_attempts', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::FAILED], true);
    }

    public function canMoveTo(string $status): bool
    {
        if ($this->isFinal()) {
            return false;
        }

        return (self::RANK[$status] ?? -1) > self::RANK[$this->status];
    }
}
