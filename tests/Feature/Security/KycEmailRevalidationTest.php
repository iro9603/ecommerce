<?php

use App\Models\Product;
use App\Models\StoreAutoApprovalAudit;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

function reviewedTrustedProduct(): array
{
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial security review.');
    $moderation->approve(
        $product,
        null,
        'Approved before seller eligibility changed.',
        $review->version,
    );

    return compact('vendor', 'product');
}

test('losing KYC revokes trust and invalidates product reviews without automatic restoration', function () {
    Queue::fake();
    $fixture = reviewedTrustedProduct();
    $vendor = $fixture['vendor'];
    $product = $fixture['product'];

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    $vendor['kyc']->forceFill(['status' => 'rejected'])->save();

    $product->refresh();
    $audit = StoreAutoApprovalAudit::query()->sole();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and($audit->admin_id)->toBeNull()
        ->and($audit->eligibility_snapshot['trigger'])->toBe('kyc_status_changed');

    $vendor['kyc']->forceFill(['status' => 'approved'])->save();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('changing a seller email revokes trust and invalidates product reviews without automatic restoration', function () {
    Queue::fake();
    $fixture = reviewedTrustedProduct();
    $vendor = $fixture['vendor'];
    $product = $fixture['product'];

    expect(Product::query()->published()->whereKey($product)->exists())->toBeTrue();

    $vendor['user']->forceFill([
        'email' => 'new-seller@example.test',
        'email_verified_at' => null,
    ])->save();

    $product->refresh();
    $audit = StoreAutoApprovalAudit::query()->sole();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and($audit->admin_id)->toBeNull()
        ->and($audit->eligibility_snapshot['trigger'])->toBe('seller_email_changed');

    $vendor['user']->forceFill(['email_verified_at' => now()])->save();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});
