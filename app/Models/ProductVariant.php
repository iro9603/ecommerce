<?php

namespace App\Models;

use App\Services\ProductPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'manage_stock' => 'boolean',
            'in_stock' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_variant_attribute_value')
            ->withPivot('attribute_value_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attribute_value')
            ->withPivot('attribute_id');
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
            null,
            null,
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
        return (bool) $this->manage_stock;
    }

    public function stockQuantity(): ?int
    {
        return $this->qty !== null ? (int) $this->qty : null;
    }

    public function inStock(): bool
    {
        if ($this->managesStock()) {
            return $this->stockQuantity() > 0;
        }

        return (bool) $this->in_stock;
    }

    public function canPurchase(?Carbon $now = null): bool
    {
        $price = $this->effectivePrice($now);

        return $this->inStock() && $price !== null && $price >= 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(?Carbon $now = null): array
    {
        return [
            'id' => $this->id,
            'regular_price' => $this->regularPrice(),
            'special_price' => $this->specialPrice(),
            'effective_price' => $this->effectivePrice($now),
            'has_active_special' => $this->hasActiveSpecial($now),
            'manage_stock' => $this->managesStock(),
            'stock_quantity' => $this->stockQuantity(),
            'in_stock' => $this->inStock(),
            'can_purchase' => $this->canPurchase($now),
            'sku' => $this->sku,
            'is_default' => (bool) $this->is_default,
            'attribute_values' => $this->attributeValues->pluck('id')->values()->all(),
        ];
    }
}
