<?php

namespace App\Observers;

use App\Models\Store;

class StoreSoftDeleteModerationObserver
{
    public function deleting(Store $store): void
    {
        if ($store->isForceDeleting()) {
            return;
        }

        $store->forceFill([
            'status' => Store::STATUS_PENDING,
            'is_active' => false,
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'reviewed_version' => null,
            'moderation_fingerprint' => null,
            'moderation_reason' => 'Store was soft deleted and must be reviewed before publication.',
        ]);
    }

    public function restored(Store $store): void
    {
        $store->forceFill([
            'status' => Store::STATUS_PENDING,
            'is_active' => false,
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'reviewed_version' => null,
            'moderation_fingerprint' => null,
            'moderation_reason' => 'Store was restored and must be reviewed before publication.',
        ])->saveQuietly();
    }
}
