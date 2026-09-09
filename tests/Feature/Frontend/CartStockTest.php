<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Tests\Support\ProductSecurityFixtures;

test('quick view and cart respect the explicit stock status of a managed variant', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $variant = ProductVariant::forceCreate([
        'product_id' => $product->getKey(),
        'name' => 'Blue/S',
        'price' => 100,
        'special_price' => 90,
        'sku' => 'B0BZ8X4S9J',
        'manage_stock' => true,
        'qty' => 5,
        'in_stock' => false,
        'is_default' => false,
        'is_active' => true,
        'position' => 1,
    ]);

    $modalResponse = $this->actingAs($vendor['user'])
        ->postJson(route('cart.add'), ['product_id' => $product->getKey()])
        ->assertOk()
        ->assertJsonPath('has_variant', true);

    preg_match(
        '/<script type=\x22application\/json\x22 id=\x22variants-data\x22>(.*?)<\/script>/s',
        $modalResponse->json('modal'),
        $matches,
    );

    expect($matches)->toHaveCount(2);

    $payload = collect(json_decode($matches[1], true))
        ->firstWhere('id', $variant->getKey());

    expect($payload['in_stock'])->toBeFalse()
        ->and($payload['can_purchase'])->toBeFalse();

    $this->postJson(route('cart.add'), [
        'product_id' => $product->getKey(),
        'variant_id' => $variant->getKey(),
        'quantity' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    $this->assertDatabaseMissing('carts', [
        'user_id' => $vendor['user']->getKey(),
        'product_id' => $product->getKey(),
        'variant_id' => $variant->getKey(),
    ]);
});
