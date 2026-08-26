<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductMediaStorageService
{
    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/avif' => 'avif',
    ];

    public function disk(): string
    {
        $disk = (string) config('products.media.disk', 'private');
        $allowed = (array) config('products.media.allowed_disks', ['private']);
        $configuration = config('filesystems.disks.'.$disk);

        if (! in_array($disk, $allowed, true) || ! is_array($configuration)) {
            throw new RuntimeException('The product media disk is not allowlisted.');
        }

        if (
            ($configuration['visibility'] ?? 'private') === 'public'
            || (bool) ($configuration['serve'] ?? false)
            || ($configuration['throw'] ?? false) !== true
        ) {
            throw new RuntimeException('The product media disk must be private and fail-fast.');
        }

        $driver = $configuration['driver'] ?? null;

        if (! in_array($driver, ['local', 's3'], true)) {
            throw new RuntimeException('The product media disk driver is not supported.');
        }

        if ($driver === 'local') {
            $root = $this->canonicalRoot((string) ($configuration['root'] ?? ''));

            foreach ([public_path(), storage_path('app/public')] as $publicRoot) {
                $publicRoot = $this->canonicalRoot($publicRoot);

                if ($root === $publicRoot || str_starts_with($root, $publicRoot.'/')) {
                    throw new RuntimeException('The product media disk root is publicly reachable.');
                }
            }
        }

        return $disk;
    }

    public function store(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['image' => 'The image could not be uploaded.']);
        }

        $mime = $file->getMimeType();
        $extension = is_string($mime) ? (self::ALLOWED_MIME_TYPES[$mime] ?? null) : null;

        if ($extension === null) {
            throw ValidationException::withMessages(['image' => 'This image type is not allowed.']);
        }

        $path = $file->storeAs('uploads', Str::uuid().'.'.$extension, $this->disk());

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the product image.');
        }

        return $this->normalizePath($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk())->delete($this->normalizePath($path));
    }

    /** @return resource */
    public function readStream(string $path)
    {
        $stream = Storage::disk($this->disk())->readStream($this->normalizePath($path));

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read the product image.');
        }

        return $stream;
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk())->exists($this->normalizePath($path));
    }

    public function mimeType(string $path): string
    {
        $mime = Storage::disk($this->disk())->mimeType($this->normalizePath($path));

        if (! is_string($mime) || ! array_key_exists($mime, self::ALLOWED_MIME_TYPES)) {
            throw new RuntimeException('The stored product media type is not allowed.');
        }

        return $mime;
    }

    public function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');

        if (
            $path === ''
            || ! str_starts_with($path, 'uploads/')
            || in_array('..', explode('/', $path), true)
        ) {
            throw new RuntimeException('The product media path is invalid.');
        }

        return $path;
    }

    public function extensionForLocalFile(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = is_string($mime) ? (self::ALLOWED_MIME_TYPES[$mime] ?? null) : null;

        if ($extension === null) {
            throw new RuntimeException('The legacy product media type is not allowed.');
        }

        return $extension;
    }

    private function canonicalRoot(string $path): string
    {
        $path = trim($path);
        $resolved = $path === '' ? false : realpath($path);

        if ($resolved === false || ! is_dir($resolved)) {
            throw new RuntimeException(
                'The product media disk root must be an existing directory.'
            );
        }

        $resolved = str_replace('\\', '/', rtrim($resolved, '/\\'));
        $resolved = $resolved === '' ? '/' : $resolved;

        return PHP_OS_FAMILY === 'Windows' ? strtolower($resolved) : $resolved;
    }
}
