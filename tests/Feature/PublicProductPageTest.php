<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProductSecurityFixtures;

final class PublicProductPageTestHelpers
{
    public static function publishedProduct(array $overrides = []): Product
    {
        $vendor = ProductSecurityFixtures::vendor();

        return ProductSecurityFixtures::product($vendor['store'], array_merge([
            'name' => 'Published Product '.str()->uuid(),
            'slug' => 'published-product-'.str()->uuid(),
            'approved_status' => Product::APPROVAL_APPROVED,
            'status' => 'active',
            'moderation_version' => 1,
            'reviewed_version' => 1,
            'price' => 100,
            'manage_stock' => 'no',
            'in_stock' => true,
        ], $overrides));
    }

    /**
     * @return array{attribute: Attribute, values: array<int, AttributeValue>}
     */
    public static function attribute(Product $product, string $name, array $valueNames): array
    {
        $attribute = Attribute::forceCreate([
            'name' => $name,
            'type' => 'text',
        ]);

        $values = [];
        foreach ($valueNames as $valueName) {
            $value = AttributeValue::forceCreate([
                'attribute_id' => $attribute->getKey(),
                'value' => $valueName,
                'color' => null,
            ]);

            DB::table('product_attribute_values')->insert([
                'product_id' => $product->getKey(),
                'attribute_id' => $attribute->getKey(),
                'attribute_value_id' => $value->getKey(),
            ]);

            $values[] = $value;
        }

        return compact('attribute', 'values');
    }

    /**
     * @param  array<int, AttributeValue>  $values
     */
    public static function variant(Product $product, array $values, array $overrides = []): ProductVariant
    {
        $variant = ProductVariant::forceCreate(array_merge([
            'product_id' => $product->getKey(),
            'name' => 'Variant '.str()->uuid(),
            'price' => 100,
            'special_price' => null,
            'sku' => 'SKU-'.str()->uuid(),
            'manage_stock' => false,
            'qty' => null,
            'in_stock' => true,
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
        ], $overrides));

        foreach ($values as $value) {
            DB::table('product_variant_attribute_value')->insert([
                'product_variant_id' => $variant->getKey(),
                'attribute_id' => $value->attribute_id,
                'attribute_value_id' => $value->getKey(),
            ]);
        }

        return $variant;
    }

    public static function loadVariants(Product $product): void
    {
        $product->load(['variants.attributeValues']);
    }
}

test('the default variant is chosen from is_default rather than the first variant', function () {
    $product = PublicProductPageTestHelpers::publishedProduct(['price' => 120]);
    ['attribute' => $attribute, 'values' => $values] = PublicProductPageTestHelpers::attribute($product, 'Size', ['Small', 'Large']);

    $first = PublicProductPageTestHelpers::variant($product, [$values[0]], [
        'name' => 'Small',
        'position' => 1,
        'is_default' => false,
    ]);
    $default = PublicProductPageTestHelpers::variant($product, [$values[1]], [
        'name' => 'Large',
        'position' => 2,
        'is_default' => true,
    ]);

    PublicProductPageTestHelpers::loadVariants($product);

    expect($product->defaultVariant()?->getKey())->toBe($default->getKey())
        ->and($product->defaultVariant()?->getKey())->not->toBe($first->getKey());
});

test('multiple default variants resolve deterministically by position and id', function () {
    $product = PublicProductPageTestHelpers::publishedProduct();
    ['attribute' => $attribute, 'values' => $values] = PublicProductPageTestHelpers::attribute($product, 'Color', ['Blue', 'Red']);

    $later = PublicProductPageTestHelpers::variant($product, [$values[0]], [
        'name' => 'Blue',
        'position' => 2,
        'is_default' => true,
    ]);
    $earlier = PublicProductPageTestHelpers::variant($product, [$values[1]], [
        'name' => 'Red',
        'position' => 1,
        'is_default' => true,
    ]);

    PublicProductPageTestHelpers::loadVariants($product);

    expect($product->defaultVariant()?->getKey())->toBe($earlier->getKey())
        ->and($product->defaultVariant()?->getKey())->not->toBe($later->getKey());
});

test('inactive variants are excluded from the public payload', function () {
    $product = PublicProductPageTestHelpers::publishedProduct();
    ['attribute' => $attribute, 'values' => $values] = PublicProductPageTestHelpers::attribute($product, 'Size', ['Small', 'Large']);

    $active = PublicProductPageTestHelpers::variant($product, [$values[0]], ['is_active' => true]);
    $inactive = PublicProductPageTestHelpers::variant($product, [$values[1]], ['is_active' => false]);

    PublicProductPageTestHelpers::loadVariants($product);

    $ids = collect($product->publicVariantPayloads())->pluck('id')->all();

    expect($ids)->toContain($active->getKey())
        ->and($ids)->not->toContain($inactive->getKey());
});

test('simple physical product stock follows the manage_stock contract', function () {
    $managed = PublicProductPageTestHelpers::publishedProduct([
        'manage_stock' => 'yes',
        'qty' => 5,
        'in_stock' => true,
    ]);
    $unmanaged = PublicProductPageTestHelpers::publishedProduct([
        'manage_stock' => 'no',
        'qty' => null,
        'in_stock' => true,
    ]);
    $out = PublicProductPageTestHelpers::publishedProduct([
        'manage_stock' => 'yes',
        'qty' => 0,
        'in_stock' => true,
    ]);

    expect($managed->canPurchase())->toBeTrue()
        ->and($unmanaged->canPurchase())->toBeTrue()
        ->and($out->canPurchase())->toBeFalse();
});

test('promotions are resolved by their start and end dates', function () {
    $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));

    $future = PublicProductPageTestHelpers::publishedProduct([
        'special_price' => 80,
        'special_price_start' => '2026-02-01',
        'special_price_end' => '2026-02-28',
    ]);
    $active = PublicProductPageTestHelpers::publishedProduct([
        'special_price' => 80,
        'special_price_start' => '2026-01-01',
        'special_price_end' => '2026-01-31',
    ]);
    $expired = PublicProductPageTestHelpers::publishedProduct([
        'special_price' => 80,
        'special_price_start' => '2025-01-01',
        'special_price_end' => '2026-01-10',
    ]);
    $noStart = PublicProductPageTestHelpers::publishedProduct([
        'special_price' => 80,
        'special_price_start' => null,
        'special_price_end' => '2026-12-31',
    ]);
    $noEnd = PublicProductPageTestHelpers::publishedProduct([
        'special_price' => 80,
        'special_price_start' => '2026-01-01',
        'special_price_end' => null,
    ]);

    expect($future->pricing()['has_active_special'])->toBeFalse()
        ->and($active->pricing()['has_active_special'])->toBeTrue()
        ->and($active->pricing()['effective_price'])->toBe(80.0)
        ->and($expired->pricing()['has_active_special'])->toBeFalse()
        ->and($noStart->pricing()['has_active_special'])->toBeTrue()
        ->and($noEnd->pricing()['has_active_special'])->toBeTrue();
});

test('the public detail page uses the store currency and no old-price class without a special', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['currency' => 'MXN']);
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Currency Product',
        'slug' => 'currency-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'price' => 120,
        'special_price' => null,
        'manage_stock' => 'no',
        'in_stock' => true,
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('MXN')
        ->assertSee('120.00')
        ->assertDontSee('current-price text-brand">MXN120.00</span><span class="old-price', false);
});

test('the public detail page renders empty states for missing content', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Empty Product',
        'slug' => 'empty-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'price' => null,
        'short_description' => null,
        'description' => '',
        'manage_stock' => 'no',
        'in_stock' => false,
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Precio no disponible', false)
        ->assertSee('No description available.', false)
        ->assertSee('No tags', false);
});

test('related products use published scope and exclude the current product', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $category = Category::forceCreate([
        'name' => 'Related Category',
        'slug' => 'related-category',
        'is_active' => true,
        'position' => 1,
    ]);

    $current = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Current Product',
        'slug' => 'current-product',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $publishedRelated = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Published Related',
        'slug' => 'published-related',
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $pendingRelated = ProductSecurityFixtures::product($vendor['store'], [
        'name' => 'Pending Related',
        'slug' => 'pending-related',
        'approved_status' => Product::APPROVAL_PENDING,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => null,
    ]);

    foreach ([$current, $publishedRelated, $pendingRelated] as $product) {
        $product->categories()->attach($category->getKey());
    }

    $response = $this->get(route('products.show', $current->slug));
    $relatedProducts = $response->viewData('relatedProducts');

    expect($relatedProducts->pluck('id'))->toContain($publishedRelated->getKey())
        ->and($relatedProducts->pluck('id'))->not->toContain($current->getKey())
        ->and($relatedProducts->pluck('id'))->not->toContain($pendingRelated->getKey());
});

test('product detail is scoped and no global product-price replacement remains', function () {
    $product = PublicProductPageTestHelpers::publishedProduct();

    $response = $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('id="product-detail"', false)
        ->assertSee('id="product-price"', false);

    expect($response->getContent())->not->toContain("$('.product-price').replaceWith");
});

test('the public variant payload is emitted as valid JSON', function () {
    $product = PublicProductPageTestHelpers::publishedProduct();
    ['attribute' => $attribute, 'values' => $values] = PublicProductPageTestHelpers::attribute($product, 'Size', ['Small', 'Large']);
    PublicProductPageTestHelpers::variant($product, [$values[0]], ['is_default' => true]);
    PublicProductPageTestHelpers::variant($product, [$values[1]], ['is_default' => false]);

    $html = $this->get(route('products.show', $product->slug))->assertOk()->getContent();

    preg_match('/<script type="application\/json" id="variants-data">(.*?)<\/script>/s', $html, $matches);

    expect($matches)->toHaveCount(2)
        ->and(json_decode($matches[1], true))->toBeArray();
});

test('public product detail avoids obvious N+1 queries', function () {
    $product = PublicProductPageTestHelpers::publishedProduct();
    ['attribute' => $attribute, 'values' => $values] = PublicProductPageTestHelpers::attribute($product, 'Size', ['Small', 'Medium', 'Large']);

    foreach ($values as $value) {
        PublicProductPageTestHelpers::variant($product, [$value], ['is_default' => $value === $values[0]]);
    }

    PublicProductPageTestHelpers::loadVariants($product);
    DB::enableQueryLog();
    $this->get(route('products.show', $product->slug))->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(60);
});

test('unpublished products continue to return 404', function () {
    $product = PublicProductPageTestHelpers::publishedProduct();
    $product->forceFill([
        'approved_status' => Product::APPROVAL_PENDING,
        'reviewed_version' => null,
    ])->save();

    $this->get(route('products.show', $product->slug))->assertNotFound();
});
