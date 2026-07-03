<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

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
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'settings' => 'array',
            'is_featured' => 'boolean',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
