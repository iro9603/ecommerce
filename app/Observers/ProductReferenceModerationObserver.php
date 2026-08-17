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
            $reference instanceof Brand => Product::query()
                ->where('brand_id', $reference->getKey())
                ->pluck('id'),
            $reference instanceof Category, $reference instanceof Tag => $reference
                ->products()
                ->pluck('products.id'),
            default => collect(),
        };

        $reason = class_basename($reference).' reference changed after product submission.';

        $productIds
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->chunk(250)
            ->each(fn ($ids) => ReevaluateProductsAfterReferenceChange::dispatch(
                $ids->values()->all(),
                $reason,
            )->afterCommit());
    }
}
