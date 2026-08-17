<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

test('a vendor cannot edit an attribute or value shared with another store', function () {
    Queue::fake();

    $vendor = ProductSecurityFixtures::vendor();
    $otherVendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store']);
    $otherProduct = ProductSecurityFixtures::product($otherVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $attribute = Attribute::forceCreate([
        'name' => 'Shared color',
        'type' => 'color',
    ]);
    $attributeValue = AttributeValue::forceCreate([
        'attribute_id' => $attribute->getKey(),
        'value' => 'Blue',
        'color' => '#0000FF',
    ]);

    foreach ([$product, $otherProduct] as $attachedProduct) {
        DB::table('product_attribute_values')->insert([
            'product_id' => $attachedProduct->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $attributeValue->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this
        ->actingAs($vendor['user'])
        ->postJson(route('vendor.products.attributes.store', $product), [
            'attribute_id' => $attribute->getKey(),
            'attribute_name' => 'Hijacked attribute',
            'attribute_type' => 'text',
            'label' => ['Hijacked value'],
            'value_id' => [$attributeValue->getKey()],
        ]);

    $response->assertForbidden();

    expect($attribute->fresh()->name)->toBe('Shared color')
        ->and($attribute->fresh()->type)->toBe('color')
        ->and($attributeValue->fresh()->value)->toBe('Blue')
        ->and($attributeValue->fresh()->color)->toBe('#0000FF')
        ->and($otherProduct->fresh()->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($otherProduct->fresh()->moderation_version)->toBe(1);
});
