<?php

use App\Jobs\EvaluateProductForApproval;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\ProductImage;
use App\Models\Store;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

function productReadyForAutomaticApproval(Store $store): Product
{
    $identifier = Str::uuid()->toString();
    $product = ProductSecurityFixtures::product($store);
    $category = Category::forceCreate([
        'name' => 'Approval Category '.$identifier,
        'slug' => 'approval-category-'.$identifier,
        'is_active' => true,
    ]);
    $product->categories()->attach($category);
    ProductImage::forceCreate([
        'product_id' => $product->getKey(),
        'path' => 'uploads/approval-'.$identifier.'.png',
        'order' => 1,
    ]);

    return $product;
}

test('the evaluation job automatically approves a low risk product from a trusted store', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true]
    );
    $product = productReadyForAutomaticApproval($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Ready for automatic review.');

    (new EvaluateProductForApproval($product->getKey(), $review->version))
        ->handle($moderation);

    $product->refresh();
    $review->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->reviewed_version)->toBe($review->version)
        ->and($product->approved_at)->not->toBeNull()
        ->and($product->approved_by)->toBeNull()
        ->and($product->risk_score)->toBe(0)
        ->and($product->risk_level)->toBe('low')
        ->and($review->status)->toBe(ProductApprovalReview::STATUS_APPROVED)
        ->and($review->source)->toBe(ProductApprovalReview::SOURCE_AUTOMATIC)
        ->and($review->risk_score)->toBe(0)
        ->and($review->risk_level)->toBe('low')
        ->and($review->risk_reasons)->toBe([])
        ->and($review->reviewed_by)->toBeNull()
        ->and($review->reviewed_at)->not->toBeNull();
});

test('the evaluation job keeps a product pending when its store is not trusted', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => false]
    );
    $product = productReadyForAutomaticApproval($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Requires store trust.');

    (new EvaluateProductForApproval($product->getKey(), $review->version))
        ->handle($moderation);

    $product->refresh();
    $review->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_at)->toBeNull()
        ->and($product->risk_score)->toBe(0)
        ->and($product->risk_level)->toBe('low')
        ->and($review->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($review->source)->toBe(ProductApprovalReview::SOURCE_AUTOMATIC)
        ->and($review->risk_score)->toBe(0)
        ->and($review->risk_level)->toBe('low')
        ->and(array_column($review->risk_reasons, 'code'))
        ->toContain('store_requires_manual_review');
});

test('the evaluation job keeps a product without a sellable price pending', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true]
    );
    $product = productReadyForAutomaticApproval($vendor['store']);
    $product->forceFill(['price' => null])->save();
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Missing sellable price.');

    (new EvaluateProductForApproval($product->getKey(), $review->version))
        ->handle($moderation);

    $product->refresh();
    $review->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->risk_level)->toBe('critical')
        ->and(array_column($review->risk_reasons, 'code'))
        ->toContain('missing_sellable_price');
});
