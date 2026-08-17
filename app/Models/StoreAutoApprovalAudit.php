<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAutoApprovalAudit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_value' => 'boolean',
            'new_value' => 'boolean',
            'eligibility_snapshot' => 'array',
            'pending_products_resubmitted' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
