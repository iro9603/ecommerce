<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

test('moderation versions invalidate an approval after product content changes', function () {
    Queue::fake();

    $vendor = ProductSecurityFixtures::vendor();
    $admin = ProductSecurityFixtures::admin();
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);

    $firstSubmission = $moderation->submit($product, $vendor['user'], 'Initial submission.');

    expect($firstSubmission->version)->toBe(1)
        ->and($firstSubmission->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($product->moderation_version)->toBe(1)
        ->and($product->submitted_at)->not->toBeNull();

    $approvedReview = $moderation->approve(
        $product,
        $admin,
        'Content reviewed by an administrator.',
        1
    );

    expect($approvedReview)->not->toBeNull()
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->reviewed_version)->toBe(1)
        ->and($product->approved_by)->toBe($admin->getKey())
        ->and($product->approved_at)->not->toBeNull()
        ->and($product->risk_score)->not->toBeNull()
        ->and($product->risk_level)->not->toBeNull();

    expect($approvedReview->risk_score)->toBe($product->risk_score)
        ->and($approvedReview->risk_level)->toBe($product->risk_level)
        ->and($approvedReview->risk_reasons)->toBeArray()
        ->not->toBeEmpty();

    $product->forceFill([
        'description' => '<p>Materially changed after approval.</p>',
    ])->save();

    $secondSubmission = $moderation->markForReview(
        $product,
        $vendor['user'],
        'Description changed.'
    );

    expect($secondSubmission->version)->toBe(2)
        ->and($secondSubmission->content_hash)->not->toBe($firstSubmission->content_hash)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_by)->toBeNull()
        ->and($product->approved_at)->toBeNull();

    $duplicateSubmission = $moderation->submit($product, $vendor['user'], 'Retry.');

    expect($duplicateSubmission->getKey())->toBe($secondSubmission->getKey())
        ->and($product->moderation_version)->toBe(2);

    $staleDecision = $moderation->approve($product, $admin, 'Stale approval.', 1);

    expect($staleDecision)->toBeNull()
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($firstSubmission->fresh()->status)->toBe(ProductApprovalReview::STATUS_APPROVED)
        ->and($secondSubmission->fresh()->status)->toBe(ProductApprovalReview::STATUS_PENDING);
});

test('changing an attribute value invalidates a pending moderation snapshot', function () {
    Queue::fake();

    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store']);
    $attribute = Attribute::forceCreate([
        'name' => 'Size',
        'type' => 'text',
    ]);
    $value = AttributeValue::forceCreate([
        'attribute_id' => $attribute->getKey(),
        'value' => 'Small',
        'color' => null,
    ]);
    DB::table('product_attribute_values')->insert([
        'product_id' => $product->getKey(),
        'attribute_id' => $attribute->getKey(),
        'attribute_value_id' => $value->getKey(),
    ]);
    $moderation = app(ProductModerationService::class);

    $firstSubmission = $moderation->submit($product, $vendor['user'], 'Initial attributes.');
    $value->forceFill(['value' => 'Large'])->save();
    $secondSubmission = $moderation->markForReview(
        $product,
        $vendor['user'],
        'Attribute value changed.'
    );

    expect($firstSubmission->version)->toBe(1)
        ->and($firstSubmission->snapshot['attributes'][0]['name'])->toBe('Size')
        ->and($firstSubmission->snapshot['attribute_value_definitions'][0]['value'])->toBe('Small')
        ->and($secondSubmission->version)->toBe(2)
        ->and($secondSubmission->content_hash)->not->toBe($firstSubmission->content_hash)
        ->and($secondSubmission->snapshot['attribute_value_definitions'][0]['value'])->toBe('Large')
        ->and($product->moderation_version)->toBe(2)
        ->and($firstSubmission->fresh()->status)->toBe(ProductApprovalReview::STATUS_SUPERSEDED);
});
