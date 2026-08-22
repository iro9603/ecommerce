<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'logo',
        'banner',
        'phone',
        'email',
        'short_description',
        'long_description',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'currency',
        'timezone',
        'country',
        'seo_title',
        'seo_description',
        'social_links',
        'status',
        'is_active',
        'approved_at',
        'approved_by',
        'suspended_at',
        'rejected_at',
        'rejection_reason',
        'moderation_version',
        'reviewed_version',
        'submitted_at',
        'moderation_fingerprint',
        'moderation_reason',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'settings' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'auto_approve_products' => 'boolean',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'rejected_at' => 'datetime',
            'submitted_at' => 'datetime',
            'moderation_version' => 'integer',
            'reviewed_version' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function autoApprovalAudits(): HasMany
    {
        return $this->hasMany(StoreAutoApprovalAudit::class);
    }

    public function approvalReviews(): HasMany
    {
        return $this->hasMany(StoreApprovalReview::class)->orderByDesc('created_at');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->suspended_at === null;
    }
}
