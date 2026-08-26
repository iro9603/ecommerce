<?php

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = Admin::forceCreate([
        'name' => 'Product Manager',
        'email' => 'product-manager@example.com',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);
    $this->admin->givePermissionTo(
        Permission::findOrCreate('Product Management', 'admin')
    );

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
        'approved_status' => Product::APPROVAL_PENDING,
        'approval_reason' => 'Pending review reason.',
        'moderation_version' => (int) $product->moderation_version,
        'store' => test()->store->id,
        'categories' => [test()->category->id],
        'brand' => test()->brand->id,
        'tags' => [],
    ], $overrides);
}

function productSlugRaceException(): QueryException
{
    $message = "Duplicate entry 'race-slug' for key 'products.products_slug_unique'";
    $previous = new \PDOException($message, 23000);
    $previous->errorInfo = ['23000', 1062, $message];

    return new QueryException(
        'mysql',
        'update products set slug = ? where id = ?',
        ['race-slug', 1],
        $previous,
    );
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

test('the database prevents product slug races from creating duplicates', function () {
    productForSlugTest('First Product', 'database-unique-product');

    expect(fn () => productForSlugTest('Concurrent Product', 'database-unique-product'))
        ->toThrow(QueryException::class);
});

test('a product slug constraint race becomes a slug validation response only', function () {
    $product = productForSlugTest('Race Product', 'race-product');
    $eventName = 'eloquent.updating: '.Product::class;

    Event::listen($eventName, static function (Product $updating) use ($product): void {
        if ($updating->is($product) && $updating->slug === 'race-slug') {
            throw productSlugRaceException();
        }
    });

    try {
        $response = $this
            ->actingAs($this->admin, 'admin')
            ->postJson(route('admin.products.update', $product), productUpdatePayload($product, [
                'slug' => 'race-slug',
            ]));
    } finally {
        Event::forget($eventName);
    }

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    expect($product->fresh()->slug)->toBe('race-product');
});

test('a stale admin review cannot overwrite a newer product version', function () {
    $product = productForSlugTest('Current Product', 'current-product');
    $product->forceFill([
        'description' => 'Current reviewed content.',
        'price' => 50,
        'approved_status' => Product::APPROVAL_PENDING,
        'moderation_version' => 2,
        'reviewed_version' => null,
    ])->save();

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.products.update', $product), productUpdatePayload($product, [
            'content' => 'Stale content must not win.',
            'price' => 1,
            'approved_status' => Product::APPROVAL_APPROVED,
            'moderation_version' => 1,
        ]))
        ->assertStatus(409);

    $product->refresh();

    expect($product->description)->toBe('Current reviewed content.')
        ->and((float) $product->price)->toBe(50.0)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull();
});
