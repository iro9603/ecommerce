<?php

namespace App\Observers;

use App\Jobs\ReevaluateProductsAfterReferenceChange;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;

class ProductReferenceModerationObserver
{
    public function updated(Model $reference): void
    {
        $this->reevaluateAffectedProducts($reference);
    }

    public function deleting(Model $reference): void
    {
        $this->reevaluateAffectedProducts($reference);
    }

    private function reevaluateAffectedProducts(Model $reference): void
    {
        $productIds = match (true) {
            $reference instanceof Brand => Product::withTrashed()
                ->where('brand_id', $reference->getKey())
                ->pluck('id'),
            $reference instanceof Category, $reference instanceof Tag => $reference
                ->products()
                ->withTrashed()
                ->pluck('products.id'),
            default => collect(),
        };

        $reason = class_basename($reference).' reference changed after product submission.';
        $ids = $productIds
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $ids->chunk(250)->each(function ($chunk) use ($reason): void {
            // Synchronous fail-closed barrier: previously approved products
            // must stop being publishable before any queued remoderation.
            Product::withTrashed()
                ->whereIn('id', $chunk->values()->all())
                ->where('approved_status', Product::APPROVAL_APPROVED)
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

            ReevaluateProductsAfterReferenceChange::dispatch(
                $chunk->values()->all(),
                $reason,
            )->afterCommit();
        });
    }
}
