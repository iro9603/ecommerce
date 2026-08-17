<?php

use App\Jobs\EvaluateProductForApproval;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\StoreAutoApprovalAudit;
use App\Services\ProductModerationService;
use App\Services\ProductRiskEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

function reviewedProductForSellerTypeTest(array $vendor): Product
{
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial security review.');

    $moderation->approve(
        $product,
        null,
        'Approved before the seller account type changed.',
        $review->version,
    );

    return $product->fresh();
}

test('a seller type change synchronously revokes trust and invalidates existing reviews', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = reviewedProductForSellerTypeTest($vendor);

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    $vendor['user']->forceFill(['user_type' => 'user'])->save();

    $product->refresh();
    $newReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();
    $audit = StoreAutoApprovalAudit::query()->sole();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_at)->toBeNull()
        ->and($newReview->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($newReview->snapshot['store_security']['seller_user_type'])->toBe('user')
        ->and($audit->admin_id)->toBeNull()
        ->and($audit->previous_value)->toBeTrue()
        ->and($audit->new_value)->toBeFalse()
        ->and($audit->pending_products_resubmitted)->toBe(1)
        ->and($audit->eligibility_snapshot['trigger'])->toBe('seller_user_type_changed')
        ->and($audit->eligibility_snapshot['previous_seller_user_type'])->toBe('vendor')
        ->and($audit->eligibility_snapshot['seller_user_type'])->toBe('user')
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();

    Queue::assertPushed(
        EvaluateProductForApproval::class,
        fn (EvaluateProductForApproval $job): bool => $job->productId === $product->getKey()
            && $job->moderationVersion === 2,
    );

    // A stale evaluation can never approve the newly submitted version.
    expect(app(ProductModerationService::class)->evaluatePending($product->getKey(), 1))
        ->toBeFalse();

    (new EvaluateProductForApproval($product->getKey(), 2))
        ->handle(app(ProductModerationService::class));
    $newReview->refresh();

    expect($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and(array_column($newReview->risk_reasons, 'code'))->toContain('seller_not_vendor');

    // Returning to vendor invalidates the non-vendor snapshot too, but never
    // restores the store's previous automatic-approval trust.
    $vendor['user']->forceFill(['user_type' => 'vendor'])->save();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->fresh()->moderation_version)->toBe(3)
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('published scope and risk evaluator independently reject a non-vendor seller', function () {
    $account = ProductSecurityFixtures::vendor(
        userOverrides: ['user_type' => 'user'],
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = ProductSecurityFixtures::product($account['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $assessment = app(ProductRiskEvaluator::class)->evaluate($product);

    expect(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and($assessment['auto_approvable'])->toBeFalse()
        ->and($assessment['score'])->toBe(100)
        ->and($assessment['level'])->toBe('critical')
        ->and(array_column($assessment['reasons'], 'code'))->toContain('seller_not_vendor');
});

test('seller type revalidation rolls back atomically with the account change', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = reviewedProductForSellerTypeTest($vendor);

    expect(fn () => DB::transaction(function () use ($vendor): void {
        $vendor['user']->forceFill(['user_type' => 'user'])->save();

        throw new RuntimeException('Abort the account type change.');
    }))->toThrow(RuntimeException::class);

    expect($vendor['user']->fresh()->user_type)->toBe('vendor')
        ->and($vendor['store']->fresh()->auto_approve_products)->toBeTrue()
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->fresh()->moderation_version)->toBe(1)
        ->and($product->fresh()->reviewed_version)->toBe(1)
        ->and(StoreAutoApprovalAudit::query()->exists())->toBeFalse();
});

test('a pending product never reuses a review that was already decided', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor();
    $product = reviewedProductForSellerTypeTest($vendor);
    $approvedReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 1)
        ->sole();
    $originalFingerprint = $product->moderation_fingerprint;

    // Reproduce a fail-closed transition that changes publication state
    // before a detailed moderation snapshot is generated.
    $product->forceFill([
        'approved_status' => Product::APPROVAL_PENDING,
        'reviewed_version' => null,
        'approved_at' => null,
        'approved_by' => null,
    ])->saveQuietly();

    expect($product->moderation_fingerprint)->toBe($originalFingerprint)
        ->and($approvedReview->status)->toBe(ProductApprovalReview::STATUS_APPROVED);

    app(ProductModerationService::class)->submit(
        $product,
        null,
        'Revalidate a product whose prior review was already decided.',
    );

    $newReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($product->fresh()->moderation_version)->toBe(2)
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($newReview->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($approvedReview->fresh()->status)->toBe(ProductApprovalReview::STATUS_APPROVED);
});

test('soft deleted products are invalidated before they can be restored', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = reviewedProductForSellerTypeTest($vendor);
    $product->delete();

    $vendor['user']->forceFill(['user_type' => 'user'])->save();

    $deletedProduct = Product::withTrashed()->findOrFail($product->getKey());

    expect($deletedProduct->trashed())->toBeTrue()
        ->and($deletedProduct->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($deletedProduct->moderation_version)->toBe(2)
        ->and($deletedProduct->reviewed_version)->toBeNull()
        ->and($vendor['store']->fresh()->auto_approve_products)->toBeFalse();

    $deletedProduct->restore();

    expect(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});
