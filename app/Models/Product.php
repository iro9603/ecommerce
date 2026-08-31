<?php

namespace App\Models;

use App\Services\ProductPricing;
use App\Services\SellerEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            'special_price_start' => 'date',
            'special_price_end' => 'date',
            'moderation_version' => 'integer',
            'reviewed_version' => 'integer',
            'risk_score' => 'integer',
            'reviewed_user_eligibility_epoch' => 'integer',
            'reviewed_kyc_id' => 'integer',
            'reviewed_kyc_eligibility_epoch' => 'integer',
            'reviewed_store_eligibility_epoch' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        $eligibility = app(SellerEligibilityService::class);

        return $query
            ->where($query->qualifyColumn('approved_status'), self::APPROVAL_APPROVED)
            ->where($query->qualifyColumn('status'), 'active')
            ->whereIn($query->qualifyColumn('product_type'), ['physical', 'digital'])
            ->where($query->qualifyColumn('moderation_version'), '>', 0)
            ->whereColumn(
                $query->qualifyColumn('reviewed_version'),
                $query->qualifyColumn('moderation_version')
            )
            ->where(
                $query->qualifyColumn('reviewed_policy_version'),
                (string) config('product_moderation.policy_version'),
            )
            ->whereHas('store', function (Builder $storeQuery) use ($eligibility): void {
                $eligibility->applyPublishableStoreQuery($storeQuery);
                $storeQuery
                    ->whereColumn(
                        $storeQuery->qualifyColumn('eligibility_epoch'),
                        'products.reviewed_store_eligibility_epoch',
                    )
                    ->whereHas('seller', function (Builder $sellerQuery) use ($eligibility): void {
                        $eligibility->applyEligibleSellerQuery($sellerQuery);
                        $sellerQuery
                            ->whereColumn(
                                $sellerQuery->qualifyColumn('eligibility_epoch'),
                                'products.reviewed_user_eligibility_epoch',
                            )
                            ->whereHas('kyc', function (Builder $kycQuery) use ($eligibility): void {
                                $eligibility->applyEligibleKycQuery($kycQuery);
                                $kycQuery
                                    ->whereColumn(
                                        $kycQuery->qualifyColumn('id'),
                                        'products.reviewed_kyc_id',
                                    )
                                    ->whereColumn(
                                        $kycQuery->qualifyColumn('eligibility_epoch'),
                                        'products.reviewed_kyc_eligibility_epoch',
                                    );
                            });
                    });
            });
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

    public function attributeAssignments(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * Group the product's selected attribute values by attribute.
     *
     * This is intentionally a plain method rather than an Eloquent relation
     * because the former implementation used a captured product id inside a
     * nested eager-load closure and produced incorrect results for multiple
     * products.
     */
    public function groupedAttributeValues(): Collection
    {
        $assignments = $this->relationLoaded('attributeAssignments')
            ? $this->getRelation('attributeAssignments')
            : $this->attributeAssignments()->with(['attribute', 'value'])->get();

        return $assignments
            ->groupBy('attribute_id')
            ->map(function (Collection $items): ?Attribute {
                $attribute = $items->first()?->attribute;

                if (! $attribute instanceof Attribute) {
                    return null;
                }

                $attribute->setRelation(
                    'values',
                    $items->map(fn (ProductAttributeValue $item): ?AttributeValue => $item->value)
                        ->filter()
                        ->values(),
                );

                return $attribute;
            })
            ->filter()
            ->values();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function primaryVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('position')
            ->orderBy('id');
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

    public function defaultVariant(): ?ProductVariant
    {
        $variants = $this->relationLoaded('variants')
            ? $this->getRelation('variants')
            : $this->variants()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

        return $variants
            ->filter(fn (ProductVariant $variant): bool => $variant->is_active && $variant->is_default)
            ->sortBy([
                ['position', 'asc'],
                ['id', 'asc'],
            ])
            ->first();
    }

    public function publicVariants(): Collection
    {
        $variants = $this->relationLoaded('variants')
            ? $this->getRelation('variants')
            : $this->variants()
                ->where('is_active', true)
                ->with('attributeValues')
                ->orderBy('position')
                ->orderBy('id')
                ->get();

        return $variants
            ->filter(fn (ProductVariant $variant): bool => $variant->is_active)
            ->sortBy([
                ['position', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicVariantPayloads(?Carbon $now = null): array
    {
        return $this->publicVariants()
            ->map(fn (ProductVariant $variant): array => $variant->publicPayload($now))
            ->all();
    }

    /**
     * @return array{
     *     regular_price: float|null,
     *     special_price: float|null,
     *     effective_price: float|null,
     *     has_active_special: bool
     * }
     */
    public function pricing(?Carbon $now = null): array
    {
        return ProductPricing::resolve(
            $this->regularPrice(),
            $this->specialPrice(),
            $this->special_price_start,
            $this->special_price_end,
            $now,
        );
    }

    public function regularPrice(): ?float
    {
        return $this->price !== null ? (float) $this->price : null;
    }

    public function specialPrice(): ?float
    {
        return $this->special_price !== null ? (float) $this->special_price : null;
    }

    public function effectivePrice(?Carbon $now = null): ?float
    {
        return $this->pricing($now)['effective_price'];
    }

    public function hasActiveSpecial(?Carbon $now = null): bool
    {
        return $this->pricing($now)['has_active_special'];
    }

    public function managesStock(): bool
    {
        return $this->manage_stock === 'yes';
    }

    public function stockQuantity(): ?int
    {
        return $this->qty !== null ? (int) $this->qty : null;
    }

    public function isDigital(): bool
    {
        return $this->product_type === 'digital';
    }

    public function inStock(): bool
    {
        if ($this->isDigital()) {
            return (bool) $this->in_stock;
        }

        if ($this->managesStock()) {
            return $this->stockQuantity() > 0;
        }

        return (bool) $this->in_stock;
    }

    public function canPurchase(): bool
    {
        $price = $this->effectivePrice();

        return $this->inStock() && $price !== null && $price >= 0;
    }

    public function currencySymbol(): string
    {
        return $this->store?->currency ?: '$';
    }
}
