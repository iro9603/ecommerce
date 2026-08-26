<?php

use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\ProductFile;
use App\Services\DigitalProductFileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProductSecurityFixtures;

function finalizeDigitalSecurityUpload(
    Product $product,
    mixed $submittedBy,
    string $contents,
    string $name,
): ProductFile {
    $uuid = Str::uuid()->toString();
    $uploaderKey = 'security-test:'.$submittedBy->getKey();
    $result = app(DigitalProductFileUploadService::class)->storeChunk(
        $product,
        UploadedFile::fake()->createWithContent('chunk.part', $contents),
        [
            'uuid' => $uuid,
            'index' => 0,
            'total_chunks' => 1,
            'total_size' => strlen($contents),
            'original_name' => $name,
        ],
        $uploaderKey,
        static fn (Product $lockedProduct) => null,
        $submittedBy,
        'Digital security regression upload.',
    );

    return $result['product_file'];
}

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
        $result = app(DigitalProductFileUploadService::class)->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', $pdf),
            [
                'uuid' => $uuid,
                'index' => 0,
                'total_chunks' => 1,
                'total_size' => strlen($pdf),
                'original_name' => 'vendor-manual.pdf',
            ],
            $uploaderKey,
            static fn (\App\Models\Product $lockedProduct) => null,
            $vendor['user'],
            'Direct upload test moderation.',
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
        expect($product->fresh()->approved_status)->toBe('pending')
            ->and($product->fresh()->moderation_version)->toBe(1)
            ->and($product->fresh()->reviewed_version)->toBeNull();
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
        expect(fn () => app(DigitalProductFileUploadService::class)->storeChunk(
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
    $service = app(DigitalProductFileUploadService::class);
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

test('a moderation failure rolls back ProductFile metadata and removes physical bytes', function () {
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
    $event = 'eloquent.creating: '.ProductApprovalReview::class;
    Event::listen($event, static function (): never {
        throw new RuntimeException('Injected Product moderation failure.');
    });

    try {
        expect(fn () => finalizeDigitalSecurityUpload(
            $product,
            $vendor['user'],
            '%PDF-rollback-file',
            'rollback.pdf',
        ))->toThrow(RuntimeException::class, 'Injected Product moderation failure.');
    } finally {
        Event::forget($event);
    }

    $current = Product::query()->findOrFail($product->getKey());

    expect(ProductFile::query()->where('product_id', $product->getKey())->count())->toBe(0)
        ->and(Storage::disk($disk)->allFiles())->toBe([])
        ->and($current->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($current->moderation_version)->toBe(1)
        ->and($current->reviewed_version)->toBe(1);
});

test('different file bytes produce immutable paths hashes and moderation versions', function () {
    $disk = (string) config('products.digital_upload.disk');
    Storage::fake($disk);
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
    ]);
    $bytesA = '%PDF-file-identity-A';
    $bytesB = '%PDF-file-identity-B';

    $fileA = finalizeDigitalSecurityUpload($product, $vendor['user'], $bytesA, 'a.pdf');
    $fileB = finalizeDigitalSecurityUpload($product->fresh(), $vendor['user'], $bytesB, 'b.pdf');
    $reviews = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->orderBy('version')
        ->get();

    expect($fileA->path)->not->toBe($fileB->path)
        ->and($fileA->sha256)->toBe(hash('sha256', $bytesA))
        ->and($fileB->sha256)->toBe(hash('sha256', $bytesB))
        ->and($fileA->sha256)->not->toBe($fileB->sha256)
        ->and($product->fresh()->moderation_version)->toBe(2)
        ->and($reviews)->toHaveCount(2)
        ->and(collect($reviews[0]->snapshot['files'])->pluck('sha256')->all())
        ->toBe([$fileA->sha256])
        ->and(collect($reviews[1]->snapshot['files'])->pluck('sha256')->all())
        ->toBe([$fileA->sha256, $fileB->sha256]);
});

test('two uploads that pass initial Store quota preflight cannot both finalize', function () {
    $disk = (string) config('products.digital_upload.disk');
    Storage::fake($disk);
    config()->set('products.digital_upload.max_total_size_per_store_kb', 1);
    config()->set('products.digital_upload.max_total_size_per_product_kb', 2);
    $vendor = ProductSecurityFixtures::vendor();
    $productA = ProductSecurityFixtures::product($vendor['store'], ['product_type' => 'digital']);
    $productB = ProductSecurityFixtures::product($vendor['store'], ['product_type' => 'digital']);
    $service = app(DigitalProductFileUploadService::class);
    $uuidA = Str::uuid()->toString();
    $uuidB = Str::uuid()->toString();
    $keyA = 'quota-a:'.$vendor['user']->getKey();
    $keyB = 'quota-b:'.$vendor['user']->getKey();
    $first = '%PDF-'.str_repeat('A', 345);
    $secondA = str_repeat('A', 350);
    $secondB = str_repeat('B', 350);
    $metadata = static fn (string $uuid, string $name): array => [
        'uuid' => $uuid,
        'index' => 0,
        'total_chunks' => 2,
        'total_size' => 700,
        'original_name' => $name,
    ];
    $chunkRoot = storage_path('app/private/chunks');
    $folderA = $chunkRoot.'/'.hash('sha256', implode('|', [$keyA, $productA->getKey(), $uuidA]));
    $folderB = $chunkRoot.'/'.hash('sha256', implode('|', [$keyB, $productB->getKey(), $uuidB]));

    try {
        expect($service->storeChunk(
            $productA,
            UploadedFile::fake()->createWithContent('a-0.part', $first),
            $metadata($uuidA, 'a.pdf'),
            $keyA,
        )['complete'])->toBeFalse();
        expect($service->storeChunk(
            $productB,
            UploadedFile::fake()->createWithContent('b-0.part', $first),
            $metadata($uuidB, 'b.pdf'),
            $keyB,
        )['complete'])->toBeFalse();

        $completeA = $metadata($uuidA, 'a.pdf');
        $completeA['index'] = 1;
        expect($service->storeChunk(
            $productA,
            UploadedFile::fake()->createWithContent('a-1.part', $secondA),
            $completeA,
            $keyA,
            static fn (Product $lockedProduct) => null,
            $vendor['user'],
            'Quota test A.',
        )['complete'])->toBeTrue();

        $completeB = $metadata($uuidB, 'b.pdf');
        $completeB['index'] = 1;
        expect(fn () => $service->storeChunk(
            $productB,
            UploadedFile::fake()->createWithContent('b-1.part', $secondB),
            $completeB,
            $keyB,
            static fn (Product $lockedProduct) => null,
            $vendor['user'],
            'Quota test B.',
        ))->toThrow(ValidationException::class);

        expect(ProductFile::query()->count())->toBe(1)
            ->and((int) ProductFile::query()->sum('size'))->toBe(700);
    } finally {
        File::deleteDirectory($folderA);
        File::deleteDirectory($folderB);
    }
});
