<?php

namespace App\Observers;

use App\Models\Store;

class StoreEligibilityEpochObserver
{
    private const FIELDS = [
        'seller_id',
        'status',
        'is_active',
        'suspended_at',
        'moderation_version',
        'reviewed_version',
        'auto_approve_products',
    ];

    public function updating(Store $store): void
    {
        if (
            ! $store->isDirty(self::FIELDS)
            || $store->isDirty('eligibility_epoch')
        ) {
            return;
        }

        $store->eligibility_epoch = ((int) $store->getRawOriginal('eligibility_epoch')) + 1;
    }
}
