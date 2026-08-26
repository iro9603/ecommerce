<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductMediaStorageService;
use App\Services\ProductModerationService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

function productMediaPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
}

function productWithPrivateImage(array $productOverrides = []): array
{
    Storage::fake('private');
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'status' => 'approved',
        'is_active' => true,
    ]);
    $product = ProductSecurityFixtures::product($vendor['store'], array_merge([
        'status' => 'active',
    ], $productOverrides));
    $path = 'uploads/'.str()->uuid().'.png';
    Storage::disk('private')->put($path, productMediaPng());
    $image = ProductImage::forceCreate([
        'product_id' => $product->getKey(),
        'path' => $path,
        'order' => 1,
    ]);

    return compact('vendor', 'product', 'image', 'path');
}

test('anonymous product media is served only while its product is currently published', function () {
    $fixture = productWithPrivateImage();
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($fixture['product'], $fixture['vendor']['user'], 'Media publication test.');
    $moderation->approve($fixture['product'], null, 'Approved for media publication.', $review->version);

    $response = $this->get(route('product-media.show', $fixture['image']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe(productMediaPng());

    $fixture['product']->forceFill([
        'approved_status' => Product::APPROVAL_PENDING,
        'reviewed_version' => null,
    ])->save();

    $this->get(route('product-media.show', $fixture['image']))->assertNotFound();
});

test('the repository ships no flat publicly servable product images', function () {
    $publicUploads = public_path('uploads');
    $flatFiles = collect(File::files($publicUploads))
        ->reject(fn (SplFileInfo $file): bool => $file->getFilename() === '.gitkeep')
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->sort()
        ->values()
        ->all();

    expect($flatFiles)->toBe(
        [],
        'Flat files under public/uploads bypass controlled storage: '.implode(', ', $flatFiles),
    );
});

test('a pending product image remains available to its owner and authorized product admins only', function () {
    $fixture = productWithPrivateImage([
        'approved_status' => Product::APPROVAL_PENDING,
    ]);
    $stranger = ProductSecurityFixtures::vendor()['user'];

    $this->get(route('product-media.show', $fixture['image']))->assertNotFound();
    $this->actingAs($stranger, 'web')
        ->get(route('product-media.show', $fixture['image']))
        ->assertNotFound();
    $this->actingAs($fixture['vendor']['user'], 'web')
        ->get(route('product-media.show', $fixture['image']))
        ->assertOk();

    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Product Management', 'admin'));
    $this->actingAs($admin, 'admin')
        ->get(route('product-media.show', $fixture['image']))
        ->assertOk();
});

test('product media refuses a publicly served disk before writing', function () {
    config([
        'products.media.disk' => 'public',
        'products.media.allowed_disks' => ['public'],
    ]);

    expect(fn () => app(ProductMediaStorageService::class)->disk())
        ->toThrow(RuntimeException::class, 'must be private and fail-fast');
});

test('product media resolves a symlinked local root before checking public reachability', function () {
    $link = storage_path('framework/testing/product-media-public-root-'.str()->uuid());
    File::ensureDirectoryExists(dirname($link));

    if (! @symlink(public_path(), $link)) {
        $this->markTestSkipped('The test environment cannot create symbolic links.');
    }

    try {
        config([
            'filesystems.disks.product-media-symlink' => [
                'driver' => 'local',
                'root' => $link,
                'visibility' => 'private',
                'serve' => false,
                'throw' => true,
            ],
            'products.media.disk' => 'product-media-symlink',
            'products.media.allowed_disks' => ['product-media-symlink'],
        ]);

        expect(fn () => app(ProductMediaStorageService::class)->disk())
            ->toThrow(RuntimeException::class, 'root is publicly reachable');
    } finally {
        if (is_link($link)) {
            unlink($link);
        }
    }
});

test('the legacy media migrator removes the public object without changing product content identity', function () {
    Storage::fake('private');
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $path = 'uploads/'.str()->uuid().'.png';
    $publicPath = public_path($path);
    File::ensureDirectoryExists(dirname($publicPath));
    File::put($publicPath, productMediaPng());

    try {
        $image = ProductImage::forceCreate([
            'product_id' => $product->getKey(),
            'path' => $path,
            'order' => 1,
        ]);

        $this->artisan('products:migrate-media-private')->assertSuccessful();

        expect(File::exists($publicPath))->toBeFalse()
            ->and(Storage::disk('private')->get($path))->toBe(productMediaPng())
            ->and($image->fresh()->path)->toBe($path);

        $this->get(route('product-media.show', $image))->assertNotFound();
    } finally {
        File::delete($publicPath);
    }
});

test('the legacy media migrator reports failure while a public copy cannot be deleted', function () {
    Storage::fake('private');
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $path = 'uploads/'.str()->uuid().'.png';
    $publicPath = public_path($path);
    File::ensureDirectoryExists(dirname($publicPath));
    File::put($publicPath, productMediaPng());

    try {
        $image = ProductImage::forceCreate([
            'product_id' => $product->getKey(),
            'path' => $path,
            'order' => 1,
        ]);

        File::partialMock()
            ->shouldReceive('delete')
            ->once()
            ->with($publicPath)
            ->andReturnFalse();

        $this->artisan('products:migrate-media-private')
            ->expectsOutputToContain(
                'ProductImage '.$image->getKey()
                .': Unable to delete the legacy public product media object.'
            )
            ->expectsOutputToContain('Migrated: 0; failed: 1.')
            ->assertFailed();

        expect(is_file($publicPath))->toBeTrue()
            ->and(Storage::disk('private')->get($path))->toBe(productMediaPng());
    } finally {
        File::swap(new Filesystem);

        if (is_file($publicPath)) {
            unlink($publicPath);
        }
    }
});
