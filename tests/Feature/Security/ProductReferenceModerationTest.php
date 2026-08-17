<?php

use App\Jobs\ReevaluateProductsAfterReferenceChange;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

test('changing a shared catalogue reference resubmits approved products', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor();
    $brand = Brand::forceCreate([
        'name' => 'Reviewed Brand',
        'slug' => 'reviewed-brand-'.Str::uuid(),
    ]);
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'brand_id' => $brand->getKey(),
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
    ]);

    $brand->forceFill(['name' => 'Changed Brand'])->save();

    $queuedJob = null;
    Queue::assertPushed(
        ReevaluateProductsAfterReferenceChange::class,
        function (ReevaluateProductsAfterReferenceChange $job) use ($product, &$queuedJob): bool {
            $queuedJob = $job;

            return in_array($product->getKey(), $job->productIds, true);
        }
    );

    expect($queuedJob)->toBeInstanceOf(ReevaluateProductsAfterReferenceChange::class);
    $queuedJob->handle(app(ProductModerationService::class));

    $product->refresh();
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_at)->toBeNull()
        ->and($review->snapshot['brand']['name'])->toBe('Changed Brand');
});
