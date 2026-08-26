<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ProductModerationService;

class ProductSoftDeleteModerationObserver
{
    public function __construct(
        private readonly ProductModerationService $moderation,
    ) {}

    public function deleting(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }

        $product->forceFill([
            'approved_status' => Product::APPROVAL_PENDING,
            'moderation_version' => ((int) $product->moderation_version) + 1,
            'reviewed_version' => null,
            'approved_at' => null,
            'approved_by' => null,
            'moderation_reason' => 'Product was soft deleted and must be reviewed before publication.',
            'moderation_fingerprint' => null,
            'risk_level' => null,
            'risk_score' => null,
            'reviewed_user_eligibility_epoch' => null,
            'reviewed_kyc_id' => null,
            'reviewed_kyc_eligibility_epoch' => null,
            'reviewed_store_eligibility_epoch' => null,
            'reviewed_context_hash' => null,
            'reviewed_policy_version' => null,
        ])->saveQuietly();
    }

    public function restoring(Product $product): void
    {
        $product->forceFill([
            'approved_status' => Product::APPROVAL_PENDING,
            'reviewed_version' => null,
            'approved_at' => null,
            'approved_by' => null,
            'moderation_reason' => 'Product was restored and must be reviewed before publication.',
            'moderation_fingerprint' => null,
            'risk_level' => null,
            'risk_score' => null,
            'moderation_version' => ((int) $product->moderation_version) + 1,
            'reviewed_user_eligibility_epoch' => null,
            'reviewed_kyc_id' => null,
            'reviewed_kyc_eligibility_epoch' => null,
            'reviewed_store_eligibility_epoch' => null,
            'reviewed_context_hash' => null,
            'reviewed_policy_version' => null,
        ]);
    }

    public function restored(Product $product): void
    {
        try {
            $this->moderation->forceRevalidate(
                $product,
                null,
                'Product was restored and requires a new moderation decision.',
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
