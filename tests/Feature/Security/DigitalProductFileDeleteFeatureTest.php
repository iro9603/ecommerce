<?php

use App\Http\Controllers\Admin\AdminDigitalProductFileController;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

test('deleting a digital file as vendor removes the row and the file on the configured disk', function () {
    $disk = (string) config('products.digital_upload.disk', 'local');
    Storage::fake($disk);
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $file = ProductFile::forceCreate([
        'product_id' => $product->getKey(),
        'filename' => 'manual.pdf',
        'path' => 'product-files/'.$product->getKey().'/manual.pdf',
        'extension' => 'pdf',
        'size' => 123,
    ]);
    Storage::disk($disk)->put($file->path, '%PDF-test');

    $this->actingAs($vendor['user']);

    $this->delete(route('vendor.digital-products.file.destroy', [$product, $file]))
        ->assertOk();

    $this->assertDatabaseMissing('product_files', ['id' => $file->getKey()]);
    Storage::disk($disk)->assertMissing($file->path);

    $product->refresh();
    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull();
});

test('admin file deletion targets the configured upload disk and never a database value', function () {
    $disk = 's3-delete-test';
    config()->set('products.digital_upload.disk', $disk);
    config()->set('filesystems.disks.'.$disk, [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/'.$disk.'-'.Str::uuid()),
        'throw' => false,
    ]);
    Storage::fake($disk);
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
    ]);
    $file = ProductFile::forceCreate([
        'product_id' => $product->getKey(),
        'filename' => 'manual.pdf',
        'path' => 'product-files/'.$product->getKey().'/manual.pdf',
        'extension' => 'pdf',
        'size' => 123,
    ]);
    Storage::disk($disk)->put($file->path, '%PDF-test');

    $response = app(AdminDigitalProductFileController::class)->destroy(
        $product,
        $file,
        app(ProductModerationService::class),
    );

    expect($response->getStatusCode())->toBe(200);
    $this->assertDatabaseMissing('product_files', ['id' => $file->getKey()]);
    Storage::disk($disk)->assertMissing($file->path);
});

test('product_files no longer records a storage disk column', function () {
    expect(Schema::hasColumn('product_files', 'disk'))->toBeFalse();
});
