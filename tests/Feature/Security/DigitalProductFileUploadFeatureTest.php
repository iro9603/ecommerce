<?php

use App\Models\ProductFile;
use App\Services\DigitalProductFileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProductSecurityFixtures;

test('a valid single chunk PDF is assembled stored and recorded safely', function () {
    $disk = (string) config('products.digital_upload.disk', 'local');
    Storage::fake($disk);
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
    ]);
    $uuid = Str::uuid()->toString();
    $uploaderKey = 'vendor:'.$vendor['user']->getKey();
    $pdf = base64_decode(
        'JVBERi0xLjQKMSAwIG9iago8PD4+CmVuZG9iagp0cmFpbGVyCjw8Pj4KJSVFT0YK',
        true
    );
    $uploadKey = hash('sha256', implode('|', [$uploaderKey, $product->getKey(), $uuid]));
    $chunkFolder = storage_path('app/private/chunks/'.$uploadKey);

    try {
        $result = (new DigitalProductFileUploadService)->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', $pdf),
            [
                'uuid' => $uuid,
                'index' => 0,
                'total_chunks' => 1,
                'total_size' => strlen($pdf),
                'original_name' => 'vendor-manual.pdf',
            ],
            $uploaderKey
        );

        $productFile = $result['product_file'] ?? null;

        expect($result['complete'])->toBeTrue()
            ->and($productFile)->toBeInstanceOf(ProductFile::class)
            ->and($productFile->filename)->toBe('vendor-manual.pdf')
            ->and($productFile->extension)->toBe('pdf')
            ->and((int) $productFile->size)->toBe(strlen($pdf))
            ->and($productFile->sha256)->toBe(hash('sha256', $pdf))
            ->and($productFile->path)->toStartWith('product-files/'.$product->getKey().'/')
            ->toEndWith('.pdf')
            ->and(File::isDirectory($chunkFolder))->toBeFalse();

        Storage::disk($disk)->assertExists($productFile->path);
        expect(Storage::disk($disk)->get($productFile->path))->toBe($pdf);

        $this->assertDatabaseHas('product_files', [
            'id' => $productFile->getKey(),
            'product_id' => $product->getKey(),
            'filename' => 'vendor-manual.pdf',
            'path' => $productFile->path,
            'extension' => 'pdf',
            'sha256' => hash('sha256', $pdf),
        ]);
    } finally {
        File::deleteDirectory($chunkFolder);
    }
});

test('a persistent product file quota is enforced before accepting chunks', function () {
    Storage::fake((string) config('products.digital_upload.disk', 'local'));
    config()->set('products.digital_upload.max_files_per_product', 1);
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
    ]);
    ProductFile::forceCreate([
        'product_id' => $product->getKey(),
        'filename' => 'existing.pdf',
        'path' => 'product-files/'.$product->getKey().'/existing.pdf',
        'extension' => 'pdf',
        'size' => 10,
    ]);
    $uuid = Str::uuid()->toString();
    $uploaderKey = 'vendor:'.$vendor['user']->getKey();
    $uploadKey = hash('sha256', implode('|', [$uploaderKey, $product->getKey(), $uuid]));
    $chunkFolder = storage_path('app/private/chunks/'.$uploadKey);

    try {
        expect(fn () => (new DigitalProductFileUploadService)->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', '%PDF-test'),
            [
                'uuid' => $uuid,
                'index' => 0,
                'total_chunks' => 1,
                'total_size' => 9,
                'original_name' => 'second.pdf',
            ],
            $uploaderKey
        ))->toThrow(ValidationException::class)
            ->and(File::isDirectory($chunkFolder))->toBeFalse();
    } finally {
        File::deleteDirectory($chunkFolder);
    }
});

test('incomplete upload quotas are isolated by uploader and stale uploads are pruned', function () {
    Storage::fake((string) config('products.digital_upload.disk', 'local'));
    config()->set('products.digital_upload.max_active_uploads_per_uploader', 1);
    config()->set('products.digital_upload.incomplete_upload_ttl_hours', 1);
    $vendor = ProductSecurityFixtures::vendor();
    $otherVendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
    ]);
    $service = new DigitalProductFileUploadService;
    $firstUuid = Str::uuid()->toString();
    $secondUuid = Str::uuid()->toString();
    $otherUuid = Str::uuid()->toString();
    $uploaderKey = 'vendor:'.$vendor['user']->getKey();
    $otherUploaderKey = 'vendor:'.$otherVendor['user']->getKey();
    $chunkRoot = storage_path('app/private/chunks');
    $folders = collect([
        [$uploaderKey, $firstUuid],
        [$uploaderKey, $secondUuid],
        [$otherUploaderKey, $otherUuid],
    ])->map(fn (array $upload): string => $chunkRoot.'/'.hash(
        'sha256',
        implode('|', [$upload[0], $product->getKey(), $upload[1]])
    ))->all();
    $metadata = fn (string $uuid): array => [
        'uuid' => $uuid,
        'index' => 0,
        'total_chunks' => 2,
        'total_size' => 18,
        'original_name' => 'manual.pdf',
    ];

    try {
        expect($service->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', '%PDF-one'),
            $metadata($firstUuid),
            $uploaderKey
        )['complete'])->toBeFalse();

        expect(fn () => $service->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', '%PDF-two'),
            $metadata($secondUuid),
            $uploaderKey
        ))->toThrow(ValidationException::class);

        expect($service->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', '%PDF-alt'),
            $metadata($otherUuid),
            $otherUploaderKey
        )['complete'])->toBeFalse();

        touch($folders[0], now()->subHours(2)->timestamp);
        clearstatcache(true, $folders[0]);

        expect($service->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', '%PDF-new'),
            $metadata($secondUuid),
            $uploaderKey
        )['complete'])->toBeFalse()
            ->and(File::isDirectory($folders[0]))->toBeFalse()
            ->and(File::isDirectory($folders[1]))->toBeTrue()
            ->and(File::isDirectory($folders[2]))->toBeTrue();
    } finally {
        foreach ($folders as $folder) {
            File::deleteDirectory($folder);
        }
    }
});
