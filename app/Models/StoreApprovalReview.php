<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreApprovalReview extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_RESTORED = 'restored';

    public const STATUS_SUPERSEDED = 'superseded';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'store_id',
        'version',
        'status',
        'source',
        'submitted_by',
        'reviewed_by',
        'submission_reason',
        'decision_reason',
        'snapshot',
        'eligibility_snapshot',
        'content_hash',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'eligibility_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
