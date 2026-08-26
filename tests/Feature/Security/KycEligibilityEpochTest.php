<?php

use App\Models\Kyc;
use App\Models\Product;
use App\Models\SellerEligibilityEvent;
use App\Models\StoreAutoApprovalAudit;
use App\Services\ProductModerationService;
use App\Services\SellerEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

function approvedProductPinnedToEligibility(array $vendor): Product
{
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Eligibility epoch test.');
    $moderation->approve($product, null, 'Content approved.', $review->version);

    return $product->fresh();
}

test('kyc expiration is inclusive in the application timezone and query scope', function () {
    $this->travelTo(CarbonImmutable::parse('2030-12-03 23:59:59', config('app.timezone')));
    $vendor = ProductSecurityFixtures::vendor(kycOverrides: [
        'document_expiry_date' => '2030-12-03',
    ]);

    expect($vendor['kyc']->isEligibleAt())->toBeTrue()
        ->and(Kyc::query()->eligibleAt()->whereKey($vendor['kyc'])->exists())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2030-12-04 00:00:00', config('app.timezone')));

    expect($vendor['kyc']->fresh()->isEligibleAt())->toBeFalse()
        ->and(Kyc::query()->eligibleAt()->whereKey($vendor['kyc'])->exists())->toBeFalse();

    $vendor['kyc']->forceFill(['document_expiry_date' => null])->save();

    expect($vendor['kyc']->fresh()->isEligibleAt())->toBeFalse();
});

test('kyc status ABA cannot recover old trust or a product eligibility decision', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = approvedProductPinnedToEligibility($vendor);
    $version = (int) $product->moderation_version;
    $contentHash = (string) $product->moderation_fingerprint;
    $reviewedKycEpoch = (int) $product->reviewed_kyc_eligibility_epoch;

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    $vendor['kyc']->forceFill(['status' => 'rejected'])->save();
    $vendor['kyc']->forceFill(['status' => 'approved'])->save();
    $product->refresh();

    expect($vendor['kyc']->fresh()->eligibility_epoch)->toBe($reviewedKycEpoch + 2)
        ->and($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe($version)
        ->and($product->moderation_fingerprint)->toBe($contentHash)
        ->and($product->reviewed_version)->toBe($version)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and(SellerEligibilityEvent::query()->count())->toBe(2);
});

test('email verification ABA cannot recover old trust or change content version', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = approvedProductPinnedToEligibility($vendor);
    $version = (int) $product->moderation_version;
    $contentHash = (string) $product->moderation_fingerprint;
    $reviewedUserEpoch = (int) $product->reviewed_user_eligibility_epoch;

    $vendor['user']->forceFill([
        'email' => 'epoch-email@example.test',
        'email_verified_at' => null,
    ])->save();
    $vendor['user']->forceFill(['email_verified_at' => now()])->save();
    $product->refresh();

    expect($vendor['user']->fresh()->eligibility_epoch)->toBe($reviewedUserEpoch + 2)
        ->and($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe($version)
        ->and($product->moderation_fingerprint)->toBe($contentHash)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('an eligibility audit failure leaves the advanced epoch fail closed until reconciliation', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = approvedProductPinnedToEligibility($vendor);
    $version = (int) $product->moderation_version;
    $contentHash = (string) $product->moderation_fingerprint;
    $reviewedUserEpoch = (int) $product->reviewed_user_eligibility_epoch;
    $storeEpoch = (int) $vendor['store']->eligibility_epoch;
    $event = 'eloquent.creating: '.StoreAutoApprovalAudit::class;

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    Event::listen($event, static function (): never {
        throw new RuntimeException('Injected eligibility audit failure.');
    });

    try {
        expect(fn () => $vendor['user']->forceFill([
            'email_verified_at' => null,
        ])->save())->toThrow(RuntimeException::class, 'Injected eligibility audit failure.');
    } finally {
        Event::forget($event);
    }

    $user = $vendor['user']->fresh();
    $store = $vendor['store']->fresh();
    $product->refresh();
    $eligibility = app(SellerEligibilityService::class)->storeSnapshot($store);

    expect($user->email_verified_at)->toBeNull()
        ->and((int) $user->eligibility_epoch)->toBe($reviewedUserEpoch + 1)
        ->and($store->auto_approve_products)->toBeTrue()
        ->and((int) $store->auto_approval_user_epoch)->toBe($reviewedUserEpoch)
        ->and((int) $store->eligibility_epoch)->toBe($storeEpoch)
        ->and($eligibility['auto_approval_eligible'])->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe($version)
        ->and($product->moderation_fingerprint)->toBe($contentHash)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and(StoreAutoApprovalAudit::query()->exists())->toBeFalse()
        ->and(SellerEligibilityEvent::query()->exists())->toBeFalse();

    $this->artisan('security:reconcile-eligibility')->assertSuccessful();

    $store->refresh();

    expect($store->auto_approve_products)->toBeFalse()
        ->and((int) $store->eligibility_epoch)->toBe($storeEpoch + 1)
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and(SellerEligibilityEvent::query()
            ->where('trigger', 'stale_trust_reconciled')
            ->count())->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();

    $this->artisan('security:reconcile-eligibility')->assertSuccessful();

    expect(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and(SellerEligibilityEvent::query()
            ->where('trigger', 'stale_trust_reconciled')
            ->count())->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});
