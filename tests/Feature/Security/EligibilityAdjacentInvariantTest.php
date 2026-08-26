<?php

use App\Models\Product;
use App\Services\ProductRiskEvaluator;
use Tests\Support\ProductSecurityFixtures;

test('selling disabled stores cannot publish or automatically approve products', function () {
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: [
            'is_active' => false,
            'auto_approve_products' => true,
        ],
    );
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $risk = app(ProductRiskEvaluator::class)->evaluate($product);

    expect(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and($risk['auto_approvable'])->toBeFalse()
        ->and(array_column($risk['reasons'], 'code'))->toContain('store_selling_disabled');
});

test('null and unknown product types fail closed', function () {
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $nullType = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => null,
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $unknownType = ProductSecurityFixtures::product($vendor['store']);
    $unknownType->product_type = 'legacy-type';
    $nullRisk = app(ProductRiskEvaluator::class)->evaluate($nullType);
    $unknownRisk = app(ProductRiskEvaluator::class)->evaluate($unknownType);

    expect(Product::query()->published()->whereKey($nullType)->exists())->toBeFalse()
        ->and($nullRisk['auto_approvable'])->toBeFalse()
        ->and(array_column($nullRisk['reasons'], 'code'))->toContain('unknown_product_type')
        ->and($unknownRisk['auto_approvable'])->toBeFalse()
        ->and(array_column($unknownRisk['reasons'], 'code'))->toContain('unknown_product_type');
});
