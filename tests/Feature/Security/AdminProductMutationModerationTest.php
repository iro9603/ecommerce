<?php

use App\Http\Controllers\Admin\ProductController;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

function approvedProductForAdminMutation(): array
{
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(
        Permission::findOrCreate('Product Management', 'admin')
    );
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
        'approved_by' => $admin->getKey(),
    ]);

    return compact('admin', 'vendor', 'product');
}

test('an admin image upload creates a new manually approved moderation version', function () {
    Queue::fake();
    $fixture = approvedProductForAdminMutation();
    $storedPath = null;

    try {
        $response = $this
            ->actingAs($fixture['admin'], 'admin')
            ->postJson(route('admin.products.images.upload', $fixture['product']), [
                'image' => UploadedFile::fake()->image('product.png'),
            ]);

        $response->assertOk()->assertJsonPath('status', 'success');
        $storedPath = parse_url($response->json('path'), PHP_URL_PATH);
        $product = $fixture['product']->fresh();
        $review = ProductApprovalReview::query()
            ->where('product_id', $product->getKey())
            ->where('version', 2)
            ->sole();

        expect($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
            ->and($product->moderation_version)->toBe(2)
            ->and($product->reviewed_version)->toBe(2)
            ->and($product->approved_by)->toBe($fixture['admin']->getKey())
            ->and($review->status)->toBe(ProductApprovalReview::STATUS_APPROVED)
            ->and($review->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
            ->and($review->reviewed_by)->toBe($fixture['admin']->getKey());
    } finally {
        if (is_string($storedPath)) {
            File::delete(public_path(ltrim($storedPath, '/')));
        }
    }
});

test('admin image reorder and deletion each create a manually approved version', function () {
    Queue::fake();
    $fixture = approvedProductForAdminMutation();
    $first = ProductImage::forceCreate([
        'product_id' => $fixture['product']->getKey(),
        'path' => 'uploads/products/first.png',
        'order' => 1,
    ]);
    $second = ProductImage::forceCreate([
        'product_id' => $fixture['product']->getKey(),
        'path' => 'uploads/products/second.png',
        'order' => 2,
    ]);

    $this
        ->actingAs($fixture['admin'], 'admin')
        ->postJson(route('admin.products.images.reorder'), [
            'images' => [
                ['id' => $first->getKey(), 'order' => 2],
                ['id' => $second->getKey(), 'order' => 1],
            ],
        ])
        ->assertNoContent();

    $product = $fixture['product']->fresh();
    $reorderReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($first->fresh()->order)->toBe(2)
        ->and($second->fresh()->order)->toBe(1)
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBe(2)
        ->and($reorderReview->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
        ->and($reorderReview->reviewed_by)->toBe($fixture['admin']->getKey());

    $this
        ->actingAs($fixture['admin'], 'admin')
        ->deleteJson(route('admin.products.images.destroy', $first))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $product->refresh();
    $deleteReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 3)
        ->sole();

    expect(ProductImage::find($first->getKey()))->toBeNull()
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe(3)
        ->and($product->reviewed_version)->toBe(3)
        ->and($deleteReview->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
        ->and($deleteReview->reviewed_by)->toBe($fixture['admin']->getKey());
});

test('an admin variant update creates a new manually approved moderation version', function () {
    Queue::fake();
    $fixture = approvedProductForAdminMutation();
    $variant = ProductVariant::forceCreate([
        'product_id' => $fixture['product']->getKey(),
        'name' => 'Blue / Large',
        'price' => 100,
        'sku' => 'OLD-SKU',
        'manage_stock' => false,
        'in_stock' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $this
        ->actingAs($fixture['admin'], 'admin')
        ->postJson(route('admin.products.variants.update', $fixture['product']), [
            'variant_id' => $variant->getKey(),
            'variant_sku' => 'NEW-SKU',
            'variant_price' => 99.99,
            'variant_special_price' => 80,
            'variant_manage_stock' => 1,
            'variant_quantity' => 3,
            'variant_stock_status' => 'in_stock',
            'variant_is_default' => 1,
            'variant_is_active' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $product = $fixture['product']->fresh();
    $variant->refresh();
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($variant->sku)->toBe('NEW-SKU')
        ->and((float) $variant->price)->toBe(99.99)
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBe(2)
        ->and($review->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
        ->and($review->reviewed_by)->toBe($fixture['admin']->getKey());
});

test('admin attribute creation and deletion each create a manually approved version', function () {
    Queue::fake();
    $fixture = approvedProductForAdminMutation();

    $createResponse = $this
        ->actingAs($fixture['admin'], 'admin')
        ->postJson(route('admin.products.attributes.store', $fixture['product']), [
            'attribute_name' => 'Size',
            'attribute_type' => 'text',
            'label' => ['Small', 'Large'],
        ]);

    $createResponse->assertOk()->assertJsonPath('status', 'success');
    $attribute = Attribute::findOrFail($createResponse->json('attribute.id'));
    $product = $fixture['product']->fresh();
    $createReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($product->attributeValues()->count())->toBe(2)
        ->and($product->variants()->count())->toBe(2)
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBe(2)
        ->and($createReview->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
        ->and($createReview->reviewed_by)->toBe($fixture['admin']->getKey());

    $this
        ->actingAs($fixture['admin'], 'admin')
        ->deleteJson(route('admin.products.attributes.destroy', [$product, $attribute]))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $product->refresh();
    $deleteReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 3)
        ->sole();

    expect(Attribute::find($attribute->getKey()))->toBeNull()
        ->and($product->attributeValues()->exists())->toBeFalse()
        ->and($product->variants()->exists())->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->moderation_version)->toBe(3)
        ->and($product->reviewed_version)->toBe(3)
        ->and($deleteReview->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
        ->and($deleteReview->reviewed_by)->toBe($fixture['admin']->getKey());
});

test('admin attribute values are rejected before mutation when the configured limit is exceeded', function () {
    config()->set('products.variants.max_values_per_attribute', 2);
    $fixture = approvedProductForAdminMutation();

    $this
        ->actingAs($fixture['admin'], 'admin')
        ->postJson(route('admin.products.attributes.store', $fixture['product']), [
            'attribute_name' => 'Size',
            'attribute_type' => 'text',
            'label' => ['Small', 'Medium', 'Large'],
        ])
        ->assertJsonValidationErrors('label');

    $product = $fixture['product']->fresh();

    expect($product->moderation_version)->toBe(1)
        ->and($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->attributes()->exists())->toBeFalse()
        ->and(ProductApprovalReview::query()
            ->where('product_id', $product->getKey())
            ->where('version', 2)
            ->exists())->toBeFalse();
});

test('admin variant generation rejects a cartesian product above the configured limit', function () {
    config()->set('products.variants.max_combinations', 3);
    $groups = collect([
        collect(['small', 'large']),
        collect(['red', 'blue']),
    ]);

    expect(fn () => (new ProductController)->cartesianProduct($groups))
        ->toThrow(ValidationException::class, 'Too many variant combinations.');
});

test('an admin cannot mutate an attribute shared by another product', function () {
    $fixture = approvedProductForAdminMutation();
    $otherProduct = ProductSecurityFixtures::product($fixture['vendor']['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
    ]);
    $attribute = Attribute::forceCreate([
        'name' => 'Shared Size',
        'type' => 'text',
    ]);
    $value = AttributeValue::forceCreate([
        'attribute_id' => $attribute->getKey(),
        'value' => 'Small',
    ]);

    foreach ([$fixture['product'], $otherProduct] as $product) {
        DB::table('product_attribute_values')->insert([
            'product_id' => $product->getKey(),
            'attribute_id' => $attribute->getKey(),
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    $this
        ->actingAs($fixture['admin'], 'admin')
        ->postJson(route('admin.products.attributes.store', $fixture['product']), [
            'attribute_id' => $attribute->getKey(),
            'attribute_name' => 'Changed Size',
            'attribute_type' => 'text',
            'label' => ['Large'],
            'value_id' => [$value->getKey()],
        ])
        ->assertForbidden();

    expect($attribute->fresh()->name)->toBe('Shared Size')
        ->and($value->fresh()->value)->toBe('Small')
        ->and($fixture['product']->fresh()->moderation_version)->toBe(1)
        ->and($otherProduct->fresh()->moderation_version)->toBe(1);
});
