<?php

use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\Store;
use App\Models\StoreApprovalReview;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

function markProductApprovedForSoftDeleteTest(Product $product): Product
{
    $version = max(1, (int) $product->moderation_version);
    $store = Store::query()
        ->with('seller.kyc')
        ->whereKey($product->store_id)
        ->sole();
    $seller = $store->seller;
    $kyc = $seller->kyc;

    $product->forceFill([
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => $version,
        'reviewed_version' => $version,
        'approved_at' => now(),
        'moderation_fingerprint' => str_repeat('a', 64),
        'reviewed_policy_version' => (string) config('product_moderation.policy_version'),
        'reviewed_user_eligibility_epoch' => $seller->eligibility_epoch,
        'reviewed_kyc_id' => $kyc->getKey(),
        'reviewed_kyc_eligibility_epoch' => $kyc->eligibility_epoch,
        'reviewed_store_eligibility_epoch' => $store->eligibility_epoch,
    ])->saveQuietly();

    return Product::query()->whereKey($product->getKey())->sole();
}

test('restoring a soft deleted product never restores its previous approval', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['is_active' => true],
        kycOverrides: ['document_expiry_date' => now()->addYear()->toDateString()],
    );
    $product = markProductApprovedForSoftDeleteTest(
        ProductSecurityFixtures::product($vendor['store']),
    );
    $productId = (int) $product->getKey();
    $approvedVersion = (int) $product->fresh()->moderation_version;

    expect(Product::query()->published()->whereKey($productId)->exists())->toBeTrue();

    $product->delete();
    $deletedProduct = Product::withTrashed()->whereKey($productId)->sole();

    expect($deletedProduct->trashed())->toBeTrue()
        ->and($deletedProduct->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($deletedProduct->reviewed_version)->toBeNull()
        ->and($deletedProduct->moderation_fingerprint)->toBeNull()
        ->and(Product::query()->published()->whereKey($productId)->exists())->toBeFalse();

    $deletedProduct->restore();
    $restoredProduct = Product::query()->whereKey($productId)->sole();
    $pendingReview = ProductApprovalReview::query()
        ->where('product_id', $productId)
        ->where('version', $restoredProduct->moderation_version)
        ->sole();

    expect($restoredProduct->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($restoredProduct->moderation_version)->toBeGreaterThan($approvedVersion)
        ->and($restoredProduct->reviewed_version)->toBeNull()
        ->and($restoredProduct->moderation_fingerprint)->not->toBeNull()
        ->and($pendingReview->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and(Product::query()->published()->whereKey($productId)->exists())->toBeFalse();
});

test('restoring a soft deleted store never restores its previous approval', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'auto_approve_products' => true,
        'is_active' => true,
    ]);
    $store = $vendor['store'];
    $product = markProductApprovedForSoftDeleteTest(
        ProductSecurityFixtures::product($store),
    );
    $storeId = (int) $store->getKey();
    $productId = (int) $product->getKey();
    $approvedVersion = (int) $store->moderation_version;

    $store->delete();
    $deletedStore = Store::withTrashed()->whereKey($storeId)->sole();
    $invalidatedProduct = Product::query()->whereKey($productId)->sole();

    expect($deletedStore->trashed())->toBeTrue()
        ->and($deletedStore->status)->toBe(Store::STATUS_PENDING)
        ->and($deletedStore->is_active)->toBeFalse()
        ->and($deletedStore->auto_approve_products)->toBeFalse()
        ->and($deletedStore->reviewed_version)->toBeNull()
        ->and($deletedStore->moderation_fingerprint)->toBeNull()
        ->and($invalidatedProduct->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($invalidatedProduct->moderation_fingerprint)->toBeNull()
        ->and(Product::query()->published()->whereKey($productId)->exists())->toBeFalse();

    $deletedStore->restore();
    $restoredStore = Store::query()->whereKey($storeId)->sole();
    $pendingReview = StoreApprovalReview::query()
        ->where('store_id', $storeId)
        ->where('version', $restoredStore->moderation_version)
        ->sole();
    $productAfterRestore = Product::query()->whereKey($productId)->sole();

    expect($restoredStore->status)->toBe(Store::STATUS_PENDING)
        ->and($restoredStore->moderation_version)->toBeGreaterThan($approvedVersion)
        ->and($restoredStore->is_active)->toBeFalse()
        ->and($restoredStore->auto_approve_products)->toBeFalse()
        ->and($restoredStore->reviewed_version)->toBeNull()
        ->and($restoredStore->moderation_fingerprint)->not->toBeNull()
        ->and($pendingReview->status)->toBe(StoreApprovalReview::STATUS_PENDING)
        ->and($productAfterRestore->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and(Product::query()->published()->whereKey($productId)->exists())->toBeFalse();
});

test('store restore stays fail closed when remoderation fails', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'auto_approve_products' => true,
        'is_active' => true,
    ]);
    $storeId = (int) $vendor['store']->getKey();
    $vendor['store']->delete();

    $legacyDeletedStore = Store::withTrashed()->whereKey($storeId)->sole();
    $legacyDeletedStore->forceFill([
        'status' => Store::STATUS_APPROVED,
        'is_active' => true,
        'auto_approve_products' => true,
        'approved_at' => now(),
        'reviewed_version' => $legacyDeletedStore->moderation_version,
        'moderation_fingerprint' => str_repeat('a', 64),
    ])->saveQuietly();
    $staleVersion = (int) $legacyDeletedStore->moderation_version;

    StoreApprovalReview::creating(static function (): void {
        throw new RuntimeException('Injected Store remoderation failure.');
    });

    expect($legacyDeletedStore->restore())->toBeTrue();

    $restoredStore = Store::query()->whereKey($storeId)->sole();

    expect($restoredStore->status)->toBe(Store::STATUS_PENDING)
        ->and($restoredStore->moderation_version)->toBeGreaterThan($staleVersion)
        ->and($restoredStore->is_active)->toBeFalse()
        ->and($restoredStore->auto_approve_products)->toBeFalse()
        ->and($restoredStore->reviewed_version)->toBeNull()
        ->and($restoredStore->moderation_fingerprint)->toBeNull()
        ->and(StoreApprovalReview::query()
            ->where('store_id', $storeId)
            ->where('version', $restoredStore->moderation_version)
            ->exists())->toBeFalse();
});

test('product restore stays fail closed when remoderation fails', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor();
    $product = markProductApprovedForSoftDeleteTest(
        ProductSecurityFixtures::product($vendor['store']),
    );
    $productId = (int) $product->getKey();
    $product->delete();

    $legacyDeletedProduct = Product::withTrashed()->whereKey($productId)->sole();
    $legacyDeletedProduct->forceFill([
        'approved_status' => Product::APPROVAL_APPROVED,
        'approved_at' => now(),
        'reviewed_version' => $legacyDeletedProduct->moderation_version,
        'moderation_fingerprint' => str_repeat('b', 64),
    ])->saveQuietly();
    $staleVersion = (int) $legacyDeletedProduct->moderation_version;

    ProductApprovalReview::creating(static function (): void {
        throw new RuntimeException('Injected Product remoderation failure.');
    });

    expect($legacyDeletedProduct->restore())->toBeTrue();

    $restoredProduct = Product::query()->whereKey($productId)->sole();

    expect($restoredProduct->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($restoredProduct->moderation_version)->toBeGreaterThan($staleVersion)
        ->and($restoredProduct->reviewed_version)->toBeNull()
        ->and($restoredProduct->moderation_fingerprint)->toBeNull()
        ->and(ProductApprovalReview::query()
            ->where('product_id', $productId)
            ->where('version', $restoredProduct->moderation_version)
            ->exists())->toBeFalse()
        ->and(Product::query()->published()->whereKey($productId)->exists())->toBeFalse();
});
