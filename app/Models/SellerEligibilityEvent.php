<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerEligibilityEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'user_epoch' => 'integer',
            'kyc_epoch' => 'integer',
            'eligible' => 'boolean',
            'snapshot' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
