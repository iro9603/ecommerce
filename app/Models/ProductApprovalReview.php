<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductApprovalReview extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const SOURCE_AUTOMATIC = 'automatic';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'product_id',
        'version',
        'status',
        'source',
        'submitted_by',
        'reviewed_by',
        'risk_score',
        'risk_level',
        'risk_reasons',
        'submission_reason',
        'decision_reason',
        'content_hash',
        'snapshot',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'risk_score' => 'integer',
            'risk_reasons' => 'array',
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
