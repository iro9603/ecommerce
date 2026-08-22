<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'moderation_version' => 'integer',
            'reviewed_version' => 'integer',
            'risk_score' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('approved_status'), self::APPROVAL_APPROVED)
            ->where($query->qualifyColumn('status'), 'active')
            ->where($query->qualifyColumn('moderation_version'), '>', 0)
            ->whereColumn(
                $query->qualifyColumn('reviewed_version'),
                $query->qualifyColumn('moderation_version')
            )
            ->whereHas('store', fn(Builder $storeQuery) => $storeQuery
                ->where('status', 'approved')
                ->whereNull('suspended_at')
                ->where($storeQuery->qualifyColumn('moderation_version'), '>', 0)
                ->whereColumn(
                    $storeQuery->qualifyColumn('reviewed_version'),
                    $storeQuery->qualifyColumn('moderation_version')
                )
                ->whereHas('seller', fn(Builder $sellerQuery) => $sellerQuery
                    ->where('user_type', 'vendor')
                    ->whereNotNull('email_verified_at')
                    ->whereHas('kyc', fn(Builder $kycQuery) => $kycQuery
                        ->where('status', 'approved'))));
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('order');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute_values')->withPivot('attribute_value_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')->withPivot('attribute_id');
    }

    public function attributeWithValues(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute_values')
            ->distinct()
            ->orderBy('id', 'asc')
            ->with([
                'values' => function ($query) {
                    $query->whereIn('id', function ($subquery) {
                        $subquery->select('attribute_value_id')->from('product_attribute_values')->where('product_id', $this->id)->orderBy('id', 'asc');
                    });
                },
            ]);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function primaryVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', 1);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function approvalReviews(): HasMany
    {
        return $this->hasMany(ProductApprovalReview::class)->orderByDesc('version');
    }

    public function latestApprovalReview()
    {
        return $this->hasOne(ProductApprovalReview::class)->latestOfMany();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }
}
