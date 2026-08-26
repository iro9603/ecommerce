<?php

use App\Models\Product;
use App\Services\DigitalProductFileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('chunk cleanup refuses to delete a directory outside its exact root', function () {
    $base = storage_path('framework/testing/digital-upload-'.Str::uuid());
    $chunkRoot = $base.'/chunks';
    $outside = $base.'/outside';
    $sentinel = $outside.'/must-survive.txt';
    File::ensureDirectoryExists($chunkRoot);
    File::ensureDirectoryExists($outside);
    File::put($sentinel, 'keep');

    try {
        $method = new ReflectionMethod(
            DigitalProductFileUploadService::class,
            'deleteIsolatedChunkDirectory'
        );
        $service = app(DigitalProductFileUploadService::class);

        expect(fn () => $method->invoke($service, $chunkRoot, $outside))
            ->toThrow(RuntimeException::class, 'Refusing to delete an unsafe chunk directory.')
            ->and(File::exists($sentinel))->toBeTrue();
    } finally {
        File::deleteDirectory($base);
    }
});

test('a traversal filename is rejected before creating a chunk directory', function () {
    $uuid = Str::uuid()->toString();
    $uploaderKey = 'vendor:991337';
    $product = new Product;
    $product->setAttribute('id', 991337);
    $chunkRoot = storage_path('app/private/chunks');
    $uploadKey = hash('sha256', implode('|', [$uploaderKey, $product->getKey(), $uuid]));
    $chunkFolder = $chunkRoot.'/'.$uploadKey;
    $outsideSentinel = storage_path('app/private/must-survive-'.$uuid.'.txt');
    File::ensureDirectoryExists($chunkRoot);
    File::put($outsideSentinel, 'keep');

    try {
        expect(fn () => app(DigitalProductFileUploadService::class)->storeChunk(
            $product,
            UploadedFile::fake()->createWithContent('chunk.part', 'first half'),
            [
                'uuid' => $uuid,
                'index' => 0,
                'total_chunks' => 2,
                'total_size' => 20,
                'original_name' => '../../must-survive-'.$uuid.'.txt',
            ],
            $uploaderKey
        ))->toThrow(ValidationException::class)
            ->and(File::isDirectory($chunkFolder))->toBeFalse()
            ->and(File::exists($outsideSentinel))->toBeTrue()
            ->and(File::isDirectory($chunkRoot.'/must-survive-'.$uuid.'.txt'))->toBeFalse();
    } finally {
        File::deleteDirectory($chunkFolder);
        File::delete($outsideSentinel);
    }
});
