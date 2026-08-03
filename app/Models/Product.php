<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('order');
    }

    function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('order');
    }

    function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute_values')->withPivot('attribute_value_id');
    }

    function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')->withPivot('attribute_id');
    }

    function attributeWithValues(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute_values')
            ->distinct()
            ->orderBy('id', 'asc')
            ->with([
                'values' => function ($query) {
                    $query->whereIn('id', function ($subquery) {
                        $subquery->select('attribute_value_id')->from('product_attribute_values')->where('product_id', $this->id)->orderBy('id', 'asc');
                    });
                }
            ]);
    }

    function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    function primaryVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', 1);
    }

    function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
