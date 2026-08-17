<?php

use App\Http\Controllers\Frontend\VendorProductController;
use App\Models\ProductImage;
use App\Services\ProductModerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\ProductSecurityFixtures;

test('a stale owned product is reauthorized after locking and its uploaded image is compensated', function () {
    $owner = ProductSecurityFixtures::vendor();
    $newOwner = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($owner['store']);
    $staleProduct = $product->fresh();
    $uploadDirectory = public_path('uploads');
    File::ensureDirectoryExists($uploadDirectory);
    $filesBefore = collect(File::files($uploadDirectory))
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->sort()
        ->values()
        ->all();

    DB::table('products')
        ->where('id', $product->getKey())
        ->update(['store_id' => $newOwner['store']->getKey()]);

    $request = Request::create('/vendor/products/images/upload', 'POST');
    $request->files->set('image', UploadedFile::fake()->image('product.png'));
    $request->setUserResolver(fn () => $owner['user']);
    $this->actingAs($owner['user']);

    expect(fn () => app(VendorProductController::class)->uploadImages(
        $request,
        $staleProduct,
        app(ProductModerationService::class),
    ))->toThrow(AuthorizationException::class);

    $filesAfter = collect(File::files($uploadDirectory))
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->sort()
        ->values()
        ->all();

    expect($filesAfter)->toBe($filesBefore)
        ->and(ProductImage::query()->where('product_id', $product->getKey())->exists())->toBeFalse();
});

test('a stale product cannot be deleted after ownership was reassigned', function () {
    $owner = ProductSecurityFixtures::vendor();
    $newOwner = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($owner['store']);
    $staleProduct = $product->fresh();

    DB::table('products')
        ->where('id', $product->getKey())
        ->update(['store_id' => $newOwner['store']->getKey()]);

    $this->actingAs($owner['user']);

    expect(fn () => app(VendorProductController::class)->destroy($staleProduct))
        ->toThrow(AuthorizationException::class)
        ->and($product->fresh()->trashed())->toBeFalse();
});
