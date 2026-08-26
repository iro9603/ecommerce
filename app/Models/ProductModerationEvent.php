<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductModerationEvent extends Model
{
    public const TYPE_SUBMITTED = 'submitted';

    public const TYPE_SUPERSEDED = 'superseded';

    public const TYPE_CONTEXT_REEVALUATED = 'context_reevaluated';

    public const TYPE_AUTOMATIC_DECLINED = 'automatic_declined';

    public const TYPE_AUTOMATIC_APPROVED = 'automatic_approved';

    public const TYPE_MANUAL_APPROVED = 'manual_approved';

    public const TYPE_MANUAL_REJECTED = 'manual_rejected';

    protected $fillable = [
        'product_id',
        'product_approval_review_id',
        'version',
        'event_type',
        'source',
        'actor_type',
        'actor_id',
        'content_hash',
        'context_hash',
        'snapshot',
        'evaluation_context',
        'risk_result',
        'reason',
        'metadata',
        'event_key',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'actor_id' => 'integer',
            'snapshot' => 'array',
            'evaluation_context' => 'array',
            'risk_result' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Product moderation events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Product moderation events are append-only.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductApprovalReview::class, 'product_approval_review_id');
    }
}
