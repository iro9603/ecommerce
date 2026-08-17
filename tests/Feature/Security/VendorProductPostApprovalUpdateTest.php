<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

test('a vendor material update invalidates approval and creates a new moderation version', function () {
    Queue::fake();

    $vendor = ProductSecurityFixtures::vendor();
    $reviewer = ProductSecurityFixtures::admin();
    $identifier = Str::uuid()->toString();
    $brand = Brand::forceCreate([
        'name' => 'Vendor Brand '.$identifier,
        'slug' => 'vendor-brand-'.$identifier,
    ]);
    $category = Category::forceCreate([
        'name' => 'Vendor Category '.$identifier,
        'slug' => 'vendor-category-'.$identifier,
        'is_active' => true,
    ]);
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'brand_id' => $brand->getKey(),
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
        'approved_by' => $reviewer->getKey(),
    ]);
    $product->categories()->attach($category);

    $response = $this
        ->actingAs($vendor['user'])
        ->postJson(route('vendor.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => '<p>Updated vendor summary.</p>',
            'content' => '<p>Material description changed by its owner.</p>',
            'sku' => 'UPDATED-SKU',
            'price' => 125.50,
            'stock_status' => 'in_stock',
            'status' => 'active',
            'categories' => [$category->getKey()],
            'brand' => $brand->getKey(),
            'tags' => [],
        ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    $product->refresh();
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($product->description)->toBe('<p>Material description changed by its owner.</p>')
        ->and((float) $product->price)->toBe(125.5)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_at)->toBeNull()
        ->and($product->approved_by)->toBeNull()
        ->and($review->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($review->source)->toBe(ProductApprovalReview::SOURCE_AUTOMATIC)
        ->and($review->submitted_by)->toBe($vendor['user']->getKey());
});
