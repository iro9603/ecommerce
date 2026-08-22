<?php

use App\Models\Product;
use App\Models\Store;
use App\Services\ProductModerationService;
use Tests\Support\ProductSecurityFixtures;

test('restoring a soft deleted product never restores its previous approval', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial review.');
    $moderation->approve($product, null, 'Approved before deletion.', $review->version);

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    $product->delete();
    $product->restore();

    $product->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('restoring a soft deleted store never restores its previous approval', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $store = $vendor['store'];

    $store->delete();
    $store->restore();

    $store->refresh();

    expect($store->status)->toBe(Store::STATUS_PENDING)
        ->and($store->is_active)->toBeFalse()
        ->and($store->reviewed_version)->toBeNull()
        ->and($store->moderation_fingerprint)->toBeNull();
});
