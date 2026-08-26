<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductModerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReevaluateProductsAfterReferenceChange implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(
        public readonly array $productIds,
        public readonly string $reason,
    ) {}

    public function handle(ProductModerationService $moderation): void
    {
        Product::withTrashed()
            ->whereIn('id', $this->productIds)
            ->orderBy('id')
            ->each(fn (Product $product) => $moderation->markForReview(
                $product,
                null,
                $this->reason,
            ));
    }
}
