<?php

use App\Services\DigitalProductFileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

function attemptPrivateDiskChunk(string $uuid): void
{
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'product_type' => 'digital',
    ]);

    app(DigitalProductFileUploadService::class)->storeChunk(
        $product,
        UploadedFile::fake()->createWithContent('chunk.part', '%PDF-private'),
        [
            'uuid' => $uuid,
            'index' => 0,
            'total_chunks' => 2,
            'total_size' => 24,
            'original_name' => 'private.pdf',
        ],
        'vendor:'.$vendor['user']->getKey(),
    );
}

test('a public disk is rejected before any digital upload bytes are staged', function () {
    config()->set('products.digital_upload.disk', 'public');
    config()->set('products.digital_upload.allowed_disks', ['private']);
    $uuid = Str::uuid()->toString();
    $before = File::directories(storage_path('app/private/chunks'));

    expect(fn () => attemptPrivateDiskChunk($uuid))
        ->toThrow(RuntimeException::class, 'not allowlisted');

    expect(File::directories(storage_path('app/private/chunks')))->toEqual($before);
});

test('an allowlisted disk with public serving enabled is still rejected', function () {
    $disk = 'misconfigured-served-digital';
    config()->set('products.digital_upload.disk', $disk);
    config()->set('products.digital_upload.allowed_disks', [$disk]);
    config()->set('filesystems.disks.'.$disk, [
        'driver' => 'local',
        'root' => storage_path('framework/testing/private-digital'),
        'visibility' => 'private',
        'serve' => true,
        'throw' => true,
    ]);

    expect(fn () => attemptPrivateDiskChunk(Str::uuid()->toString()))
        ->toThrow(RuntimeException::class, 'must be private and fail-fast');
});

test('the production digital disk contract is private non-served and fail-fast', function () {
    $disk = (string) config('products.digital_upload.disk');
    $configuration = config('filesystems.disks.'.$disk);

    expect(config('products.digital_upload.allowed_disks'))->toContain($disk)
        ->and($configuration['visibility'])->toBe('private')
        ->and($configuration['serve'])->toBeFalse()
        ->and($configuration['throw'])->toBeTrue()
        ->and(str_replace('\\', '/', $configuration['root']))
        ->not->toStartWith(str_replace('\\', '/', public_path()));
});
