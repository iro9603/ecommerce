<?php

namespace App\Observers;

use App\Models\Product;

class ProductSoftDeleteModerationObserver
{
    public function deleting(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }

        $product->forceFill([
            'approved_status' => Product::APPROVAL_PENDING,
            'reviewed_version' => null,
            'approved_at' => null,
            'approved_by' => null,
            'moderation_reason' => 'Product was soft deleted and must be reviewed before publication.',
            'moderation_fingerprint' => null,
            'risk_level' => null,
            'risk_score' => null,
        ]);
    }

    public function restored(Product $product): void
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
        ])->saveQuietly();
    }
}
