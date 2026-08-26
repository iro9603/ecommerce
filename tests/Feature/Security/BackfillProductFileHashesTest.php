<?php

use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\ProductFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProductSecurityFixtures;

test('file hash backfill streams bytes and remoderates an approved Product atomically', function () {
    $disk = (string) config('products.digital_upload.disk');
    Storage::fake($disk);
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
    ]);
    $bytes = '%PDF-streamed-backfill-content';
    $file = ProductFile::forceCreate([
        'product_id' => $product->getKey(),
        'filename' => 'legacy.pdf',
        'path' => 'product-files/'.$product->getKey().'/legacy.pdf',
        'extension' => 'pdf',
        'size' => 1,
        'sha256' => null,
    ]);
    Storage::disk($disk)->put($file->path, $bytes);

    expect(Artisan::call('products:backfill-file-hashes', ['--batch' => 1]))->toBe(0);

    $current = Product::query()->findOrFail($product->getKey());
    $file->refresh();
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($file->sha256)->toBe(hash('sha256', $bytes))
        ->and($file->size)->toBe(strlen($bytes))
        ->and($current->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($current->moderation_version)->toBe(2)
        ->and($current->reviewed_version)->toBeNull()
        ->and(collect($review->snapshot['files'])->firstWhere('id', $file->getKey())['sha256'])
        ->toBe(hash('sha256', $bytes));
});

test('an unreadable legacy file remains hashless and its old approval is revoked', function () {
    Storage::fake((string) config('products.digital_upload.disk'));
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
    ]);
    $file = ProductFile::forceCreate([
        'product_id' => $product->getKey(),
        'filename' => 'missing.pdf',
        'path' => 'product-files/'.$product->getKey().'/missing.pdf',
        'extension' => 'pdf',
        'size' => 100,
        'sha256' => null,
    ]);

    expect(Artisan::call('products:backfill-file-hashes'))->toBe(0)
        ->and($file->fresh()->sha256)->toBeNull()
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->fresh()->moderation_version)->toBe(2)
        ->and($product->fresh()->reviewed_version)->toBeNull();
});
