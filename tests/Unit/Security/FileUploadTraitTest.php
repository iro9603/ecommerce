<?php

use App\Traits\FileUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

final class FileUploadTraitSecurityHarness
{
    use FileUploadTrait;
}

test('a real image named as PHP is stored using its MIME-derived image extension', function () {
    $harness = new FileUploadTraitSecurityHarness;
    $directory = 'testing/file-upload-trait-'.Str::uuid();
    $storedPath = null;
    $temporaryImage = tempnam(sys_get_temp_dir(), 'real-image-');

    if ($temporaryImage === false) {
        throw new RuntimeException('Unable to create the temporary image fixture.');
    }

    File::put(
        $temporaryImage,
        base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        )
    );

    try {
        $upload = new UploadedFile(
            $temporaryImage,
            'payload.php',
            'application/x-httpd-php',
            UPLOAD_ERR_OK,
            true
        );

        expect($upload->getMimeType())->toBe('image/png');

        $storedPath = $harness->uploadFile(
            $upload,
            path: $directory
        );

        expect($storedPath)->not->toBeNull()
            ->toEndWith('.png')
            ->not->toEndWith('.php')
            ->and(File::isFile(public_path($storedPath)))->toBeTrue();
    } finally {
        if (is_string($storedPath)) {
            $harness->deleteFile($storedPath);
        }

        File::deleteDirectory(public_path($directory));
        File::delete($temporaryImage);
    }
});

test('deleteFile rejects traversal without touching a sentinel outside public', function () {
    $harness = new FileUploadTraitSecurityHarness;
    $filename = 'file-upload-sentinel-'.Str::uuid().'.txt';
    $sentinel = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($sentinel));
    File::put($sentinel, 'must survive');

    try {
        expect($harness->deleteFile('../storage/framework/testing/'.$filename))->toBeFalse()
            ->and(File::get($sentinel))->toBe('must survive');
    } finally {
        File::delete($sentinel);
    }
});
