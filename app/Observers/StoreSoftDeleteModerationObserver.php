<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\Store;
use App\Services\StoreModerationService;

class StoreSoftDeleteModerationObserver
{
    public function __construct(
        private readonly StoreModerationService $moderation,
    ) {}

    public function deleting(Store $store): void
    {
        if ($store->isForceDeleting()) {
            return;
        }

        $store->forceFill([
            'status' => Store::STATUS_PENDING,
            'moderation_version' => ((int) $store->moderation_version) + 1,
            'eligibility_epoch' => ((int) $store->eligibility_epoch) + 1,
            'is_active' => false,
            'auto_approve_products' => false,
            'auto_approval_user_epoch' => null,
            'auto_approval_kyc_id' => null,
            'auto_approval_kyc_epoch' => null,
            'auto_approval_store_epoch' => null,
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'reviewed_version' => null,
            'moderation_fingerprint' => null,
            'moderation_reason' => 'Store was soft deleted and must be reviewed before publication.',
        ])->saveQuietly();

        $this->invalidateProducts(
            $store,
            'Product approval was invalidated because its Store was soft deleted.',
        );
    }

    public function restoring(Store $store): void
    {
        $store->forceFill([
            'status' => Store::STATUS_PENDING,
            'is_active' => false,
            'auto_approve_products' => false,
            'auto_approval_user_epoch' => null,
            'auto_approval_kyc_id' => null,
            'auto_approval_kyc_epoch' => null,
            'auto_approval_store_epoch' => null,
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'reviewed_version' => null,
            'moderation_fingerprint' => null,
            'moderation_reason' => 'Store was restored and must be reviewed before publication.',
            'moderation_version' => ((int) $store->moderation_version) + 1,
        ]);

        $this->invalidateProducts(
            $store,
            'Product approval was invalidated because its Store was restored.',
        );
    }

    public function restored(Store $store): void
    {
        try {
            $this->moderation->submitForReview(
                $store,
                null,
                'Store was restored and requires a new moderation decision.',
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function invalidateProducts(Store $store, string $reason): void
    {
        Product::withTrashed()
            ->where('store_id', $store->getKey())
            ->whereIn('approved_status', [
                Product::APPROVAL_APPROVED,
                Product::APPROVAL_PENDING,
            ])
            ->update([
                'approved_status' => Product::APPROVAL_PENDING,
                'reviewed_version' => null,
                'approved_at' => null,
                'approved_by' => null,
                'moderation_reason' => $reason,
                'moderation_fingerprint' => null,
                'risk_level' => null,
                'risk_score' => null,
                'updated_at' => now(),
            ]);
    }
}
