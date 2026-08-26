<?php

use App\Models\Product;
use App\Models\SellerEligibilityEvent;
use App\Models\StoreAutoApprovalAudit;
use App\Services\ProductModerationService;
use App\Services\ProductRiskEvaluator;
use App\Services\SellerEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

function approvedProductForExpiration(array $vendor): Product
{
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Expiration test.');
    $moderation->approve($product, null, 'Content approved.', $review->version);

    return $product->fresh();
}

test('expiration closes publication before the reconciler and renewal never restores trust', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2031-04-10 12:00:00', config('app.timezone')));
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
        kycOverrides: ['document_expiry_date' => '2031-04-10'],
    );
    $product = approvedProductForExpiration($vendor);
    $version = (int) $product->moderation_version;
    $contentHash = (string) $product->moderation_fingerprint;

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2031-04-11 00:00:01', config('app.timezone')));
    $risk = app(ProductRiskEvaluator::class)->evaluate($product->fresh());

    expect(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and(app(SellerEligibilityService::class)
            ->storeSnapshot($vendor['store']->fresh())['auto_approval_eligible'])->toBeFalse()
        ->and(array_column($risk['reasons'], 'code'))->toContain('seller_kyc_expired');

    $this->artisan('security:reconcile-eligibility')->assertSuccessful();

    $kyc = $vendor['kyc']->fresh();
    expect($kyc->expiration_reconciled_for?->toDateString())->toBe('2031-04-10')
        ->and($kyc->eligibility_epoch)->toBe(1)
        ->and($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and(SellerEligibilityEvent::query()->where('trigger', 'kyc_expired')->count())->toBe(1)
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1);

    $this->artisan('security:reconcile-eligibility')->assertSuccessful();

    expect(SellerEligibilityEvent::query()->where('trigger', 'kyc_expired')->count())->toBe(1)
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1);

    $kyc->forceFill(['document_expiry_date' => '2032-04-10'])->save();
    $product->refresh();

    expect($kyc->fresh()->isEligibleAt())->toBeTrue()
        ->and($kyc->fresh()->eligibility_epoch)->toBe(2)
        ->and($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->moderation_version)->toBe($version)
        ->and($product->moderation_fingerprint)->toBe($contentHash)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});
