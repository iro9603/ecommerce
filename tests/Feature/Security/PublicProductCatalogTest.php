<?php

use App\Models\Product;
use Tests\Support\ProductSecurityFixtures;

test('the public home and catalog only render products that satisfy the published scope', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $published = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Visible Published Product',
        'slug' => 'visible-published-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $pending = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Pending Private Product',
        'slug' => 'pending-private-product',
        'approved_status' => Product::APPROVAL_PENDING,
        'status' => 'active',
        'moderation_version' => 2,
        'reviewed_version' => null,
    ]);
    $stale = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Stale Approval Product',
        'slug' => 'stale-approval-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 2,
        'reviewed_version' => 1,
    ]);

    foreach ([route('home'), route('products.index')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee($published->name)
            ->assertSee(route('products.show', $published->slug))
            ->assertDontSee($pending->name)
            ->assertDontSee($stale->name);
    }
});

test('the public detail resolves a product through the published scope on every request', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Continuously Checked Product',
        'slug' => 'continuously-checked-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 3,
        'reviewed_version' => 3,
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee($product->name);

    $product->forceFill([
        'approved_status' => Product::APPROVAL_PENDING,
        'reviewed_version' => null,
    ])->save();

    $this->get(route('products.show', $product->slug))->assertNotFound();
});

test('the public storefront stops exposing a product when its seller is no longer a vendor', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Former Vendor Product',
        'slug' => 'former-vendor-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    $vendor['user']->forceFill(['user_type' => 'user'])->save();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($product->name);
    $this->get(route('products.index'))
        ->assertOk()
        ->assertDontSee($product->name);
    $this->get(route('products.show', $product->slug))->assertNotFound();
});

test('an unpublished slug can never be opened directly on the public detail', function (array $state) {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], array_merge([
        'slug' => 'private-product-'.str()->uuid(),
    ], $state));

    $this->get(route('products.show', $product->slug))->assertNotFound();
})->with([
    'pending approval' => [[
        'approved_status' => Product::APPROVAL_PENDING,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => null,
    ]],
    'inactive product' => [[
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'inactive',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]],
    'unreviewed current version' => [[
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 4,
        'reviewed_version' => 3,
    ]],
]);

test('the public storefront displays the primary variant price when the base price is empty', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Variant Price Product',
        'slug' => 'variant-price-product',
        'price' => null,
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $product->variants()->create([
        'name' => 'Default Variant',
        'price' => 42.50,
        'is_default' => true,
        'is_active' => true,
        'in_stock' => true,
    ]);

    foreach ([
        route('home'),
        route('products.index'),
        route('products.show', $product->slug),
    ] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('42.50');
    }
});

test('the vendor product workspace continues to show its own unpublished drafts', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $draft = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Vendor Pending Workspace Product',
        'slug' => 'vendor-pending-workspace-product',
        'approved_status' => Product::APPROVAL_PENDING,
        'status' => 'draft',
    ]);

    $this->actingAs($vendor['user'])
        ->get(route('vendor.products.index'))
        ->assertOk()
        ->assertSee($draft->name);
});
