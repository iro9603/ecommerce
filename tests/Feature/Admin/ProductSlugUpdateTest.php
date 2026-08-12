<?php

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->admin = Admin::forceCreate([
        'name' => 'Product Manager',
        'email' => 'product-manager@example.com',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);

    $seller = User::factory()->create();

    $this->store = Store::forceCreate([
        'seller_id' => $seller->id,
        'name' => 'Test Store',
        'slug' => 'test-store',
    ]);

    $this->brand = Brand::forceCreate([
        'name' => 'Test Brand',
        'slug' => 'test-brand',
    ]);

    $this->category = Category::forceCreate([
        'name' => 'Books',
        'slug' => 'books',
    ]);
});

function productForSlugTest(string $name, string $slug): Product
{
    $product = Product::forceCreate([
        'store_id' => test()->store->id,
        'brand_id' => test()->brand->id,
        'product_type' => 'physical',
        'name' => $name,
        'slug' => $slug,
        'description' => 'Product description.',
        'price' => 10,
        'manage_stock' => 'no',
        'in_stock' => true,
        'status' => 'active',
    ]);

    $product->categories()->attach(test()->category);

    return $product;
}

function productUpdatePayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'name' => $product->name,
        'slug' => $product->slug,
        'content' => $product->description,
        'price' => $product->price,
        'stock_status' => 'in_stock',
        'status' => 'active',
        'store' => test()->store->id,
        'categories' => [test()->category->id],
        'brand' => test()->brand->id,
        'tags' => [],
    ], $overrides);
}

test('an admin can update a product slug', function () {
    $product = productForSlugTest('Linear Algebra Done Right', 'linear-algebra-done-right');

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.products.update', $product), productUpdatePayload($product, [
            'name' => 'Linear Algebra Done Right 1',
            'slug' => 'linear-algebra-done-right-1',
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($product->fresh()->slug)->toBe('linear-algebra-done-right-1');
});

test('a product slug must be unique when it is updated', function () {
    $product = productForSlugTest('First Product', 'first-product');
    productForSlugTest('Second Product', 'second-product');

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.products.update', $product), productUpdatePayload($product, [
            'slug' => 'second-product',
        ]))
        ->assertJsonValidationErrors('slug');

    expect($product->fresh()->slug)->toBe('first-product');
});
