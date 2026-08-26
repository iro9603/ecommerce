<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DigitalProductFileUploadService
{
    public function __construct(private readonly ProductModerationService $moderation) {}

    public function disk(): string
    {
        return $this->uploadDisk();
    }

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/rtf' => 'rtf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        'application/epub+zip' => 'epub',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/flac' => 'flac',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-7z-compressed' => '7z',
    ];

    /**
     * @param  array{uuid:string,index:int,total_chunks:int,total_size:int,original_name:string}  $metadata
     * @return array{complete:bool,product_file?:ProductFile,chunk?:int}
     */
    public function storeChunk(
        Product $product,
        UploadedFile $chunk,
        array $metadata,
        string $uploaderKey,
        ?Closure $authorizeLockedProduct = null,
        ?User $submittedBy = null,
        string $moderationReason = 'Digital product file changed.',
    ): array
    {
        $this->uploadDisk();
        $this->assertMetadata($metadata);
        $this->assertPersistentQuota($product, $metadata['total_size']);

        $chunkRoot = storage_path('app/private/chunks');
        File::ensureDirectoryExists($chunkRoot, 0700, true);

        $uploaderHash = hash('sha256', $uploaderKey);
        $uploadKey = hash('sha256', implode('|', [$uploaderKey, $product->getKey(), $metadata['uuid']]));
        $chunkFolder = $chunkRoot.DIRECTORY_SEPARATOR.$uploadKey;
        $this->reserveUploadDirectory($chunkRoot, $chunkFolder, $metadata, $uploaderHash);
        $this->assertConsistentMetadata($chunkFolder, $metadata, $uploaderHash);

        $chunk->move($chunkFolder, $metadata['index'].'.part');

        if (! $this->hasEveryChunk($chunkFolder, $metadata['total_chunks'])) {
            return ['complete' => false, 'chunk' => $metadata['index']];
        }

        $lock = fopen($chunkFolder.DIRECTORY_SEPARATOR.'.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return ['complete' => false, 'chunk' => $metadata['index']];
        }

        try {
            if (! $this->hasEveryChunk($chunkFolder, $metadata['total_chunks'])) {
                return ['complete' => false, 'chunk' => $metadata['index']];
            }

            $assembledPath = $chunkFolder.DIRECTORY_SEPARATOR.'assembled.tmp';
            $this->assemble($chunkFolder, $assembledPath, $metadata['total_chunks']);
            $this->assertFinalSize($assembledPath, $metadata['total_size']);
            $this->assertPersistentQuota($product, $metadata['total_size']);
            $extension = $this->safeExtension($assembledPath);
            $sha256 = hash_file('sha256', $assembledPath);
            $relativePath = 'product-files/'.$product->getKey().'/'.Str::uuid().'.'.$extension;

            $stream = fopen($assembledPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to read the assembled upload.');
            }

            try {
                if (! Storage::disk($this->uploadDisk())->put($relativePath, $stream)) {
                    throw new RuntimeException('Unable to store the uploaded file.');
                }
            } finally {
                fclose($stream);
            }

            $size = filesize($assembledPath);

            if ($size === false) {
                Storage::disk($this->uploadDisk())->delete($relativePath);
                throw new RuntimeException('Unable to determine the assembled upload size.');
            }

           try {
                if ($authorizeLockedProduct === null) {
                    throw new RuntimeException('Locked product authorization is required to finalize an upload.');
                }

               $productFile = $this->persistFileAndModeration(
                    $product,
                    [
                        'filename' => $this->safeOriginalName($metadata['original_name']),
                        'path' => $relativePath,
                        'extension' => $extension,
                        'size' => $size,
                        'sha256' => $sha256,
                    ],
                    $authorizeLockedProduct,
                    $submittedBy,
                    $moderationReason,
                );
            } catch (\Throwable $exception) {
                Storage::disk($this->uploadDisk())->delete($relativePath);
                throw $exception;
            }

            if (DB::connection()->transactionLevel() > 0) {
                DB::connection()->afterRollBack(
                    fn () => Storage::disk($this->uploadDisk())->delete($relativePath),
                );
            }

            return ['complete' => true, 'product_file' => $productFile];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            $this->deleteIsolatedChunkDirectory($chunkRoot, $chunkFolder);
        }
    }

    /**
     * @param array{filename:string,path:string,extension:string,size:int,sha256:string} $metadata
     */
    private function persistFileAndModeration(
        Product $product,
        array $metadata,
        Closure $authorizeLockedProduct,
        ?User $submittedBy,
        string $moderationReason,
    ): ProductFile {
        $storeId = Product::query()->whereKey($product->getKey())->value('store_id');
        abort_if($storeId === null, 409, 'The product store association changed.');

        return DB::transaction(function () use (
            $product,
            $metadata,
            $authorizeLockedProduct,
            $submittedBy,
            $moderationReason,
            $storeId,
        ): ProductFile {
            $store = Store::withTrashed()
                ->whereKey((int) $storeId)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($store->trashed(), 409, 'Files cannot be added to a deleted store.');

            $current = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                (int) $current->store_id === (int) $storeId,
                409,
                'The product store association changed.',
            );
            abort_unless($current->product_type === 'digital', 404);
            $authorizeLockedProduct($current);

            ProductFile::query()
                ->where('product_id', $current->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $this->assertPersistentQuota($current, $metadata['size']);

            $productFile = ProductFile::query()->create([
                'product_id' => $current->getKey(),
                'filename' => $metadata['filename'],
                'path' => $metadata['path'],
                'extension' => $metadata['extension'],
                'size' => $metadata['size'],
                'sha256' => $metadata['sha256'],
            ]);

            $this->moderation->markForReview(
                $current,
                $submittedBy,
                $moderationReason,
            );

            return $productFile;
        }, 5);
    }

    /** @param array{uuid:string,index:int,total_chunks:int,total_size:int,original_name:string} $metadata */
    private function assertMetadata(array $metadata): void
    {
        $maxChunks = (int) config('products.digital_upload.max_chunks', 4096);
        $maxBytes = (int) config('products.digital_upload.max_file_size_kb', 262144) * 1024;

        if (
            $metadata['total_chunks'] < 1
            || $metadata['total_chunks'] > $maxChunks
            || $metadata['index'] < 0
            || $metadata['index'] >= $metadata['total_chunks']
            || $metadata['total_size'] < 1
            || $metadata['total_size'] > $maxBytes
        ) {
            throw ValidationException::withMessages(['file' => 'The upload metadata is invalid.']);
        }

        $this->safeOriginalName($metadata['original_name']);
    }

    private function hasEveryChunk(string $folder, int $totalChunks): bool
    {
        for ($index = 0; $index < $totalChunks; $index++) {
            if (! File::isFile($folder.DIRECTORY_SEPARATOR.$index.'.part')) {
                return false;
            }
        }

        return true;
    }

    /** @param array{uuid:string,index:int,total_chunks:int,total_size:int,original_name:string} $metadata */
    private function reserveUploadDirectory(
        string $root,
        string $folder,
        array $metadata,
        string $uploaderHash
    ): void {
        if (File::isDirectory($folder)) {
            return;
        }

        $lock = fopen($root.DIRECTORY_SEPARATOR.'.quota.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the upload quota.');
        }

        try {
            if (File::isDirectory($folder)) {
                return;
            }

            $activeUploads = $this->pruneAndCountActiveUploads($root, $uploaderHash);
            $maximum = max(
                1,
                (int) config('products.digital_upload.max_active_uploads_per_uploader', 3)
            );

            if ($activeUploads >= $maximum) {
                throw ValidationException::withMessages([
                    'file' => 'Too many digital uploads are already in progress.',
                ]);
            }

            File::ensureDirectoryExists($folder, 0700, true);
            File::put(
                $folder.DIRECTORY_SEPARATOR.'.metadata.json',
                json_encode($this->manifest($metadata, $uploaderHash), JSON_THROW_ON_ERROR)
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function pruneAndCountActiveUploads(string $root, string $uploaderHash): int
    {
        $maximumAge = max(
            1,
            (int) config('products.digital_upload.incomplete_upload_ttl_hours', 24)
        );
        $cutoff = time() - ($maximumAge * 3600);
        $activeUploads = 0;

        foreach (File::directories($root) as $candidate) {
            if (preg_match('/^[a-f0-9]{64}$/', basename($candidate)) !== 1) {
                continue;
            }

            if (File::lastModified($candidate) < $cutoff) {
                $this->deleteIsolatedChunkDirectory($root, $candidate);

                continue;
            }

            $manifestPath = $candidate.DIRECTORY_SEPARATOR.'.metadata.json';
            if (! File::isFile($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) File::get($manifestPath), true);
            $storedHash = is_array($manifest) ? ($manifest['uploader_hash'] ?? null) : null;

            if (is_string($storedHash) && hash_equals($uploaderHash, $storedHash)) {
                $activeUploads++;
            }
        }

        return $activeUploads;
    }

    /**
     * @param  array{uuid:string,index:int,total_chunks:int,total_size:int,original_name:string}  $metadata
     * @return array{uploader_hash:string,uuid:string,total_chunks:int,total_size:int,original_name:string}
     */
    private function manifest(array $metadata, string $uploaderHash): array
    {
        return [
            'uploader_hash' => $uploaderHash,
            'uuid' => $metadata['uuid'],
            'total_chunks' => $metadata['total_chunks'],
            'total_size' => $metadata['total_size'],
            'original_name' => $metadata['original_name'],
        ];
    }

    /** @param array{uuid:string,index:int,total_chunks:int,total_size:int,original_name:string} $metadata */
    private function assertConsistentMetadata(
        string $folder,
        array $metadata,
        string $uploaderHash
    ): void {
        $manifestPath = $folder.DIRECTORY_SEPARATOR.'.metadata.json';
        $lock = fopen($folder.DIRECTORY_SEPARATOR.'.metadata.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the upload metadata.');
        }

        try {
            $expected = $this->manifest($metadata, $uploaderHash);

            if (! File::exists($manifestPath)) {
                File::put($manifestPath, json_encode($expected, JSON_THROW_ON_ERROR));

                return;
            }

            $stored = json_decode((string) File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

            if ($stored !== $expected) {
                throw ValidationException::withMessages([
                    'file' => 'The upload metadata changed between chunks.',
                ]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function assemble(string $folder, string $destination, int $totalChunks): void
    {
        $output = fopen($destination, 'wb');
        if ($output === false) {
            throw new RuntimeException('Unable to assemble the upload.');
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $input = fopen($folder.DIRECTORY_SEPARATOR.$index.'.part', 'rb');
                if ($input === false) {
                    throw ValidationException::withMessages(['file' => 'An upload chunk is missing.']);
                }
                try {
                    stream_copy_to_stream($input, $output);
                } finally {
                    fclose($input);
                }
            }
        } finally {
            fclose($output);
        }
    }

    private function assertFinalSize(string $path, int $expectedSize): void
    {
        $actualSize = filesize($path);
        $maxBytes = (int) config('products.digital_upload.max_file_size_kb', 262144) * 1024;

        if ($actualSize === false || $actualSize !== $expectedSize || $actualSize > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file size does not match its metadata.',
            ]);
        }
    }

    private function assertPersistentQuota(Product $product, int $incomingBytes): void
    {
        $maximumFiles = max(
            1,
            (int) config('products.digital_upload.max_files_per_product', 20)
        );
        $maximumProductBytes = max(
            1,
            (int) config('products.digital_upload.max_total_size_per_product_kb', 1048576)
        ) * 1024;
        $maximumStoreBytes = max(
            1,
            (int) config('products.digital_upload.max_total_size_per_store_kb', 5242880)
        ) * 1024;

        $productFiles = ProductFile::query()
            ->where('product_id', $product->getKey());
        $fileCount = (int) (clone $productFiles)->count();
        $productBytes = (int) (clone $productFiles)->sum('size');

        if (
            $fileCount >= $maximumFiles
            || $incomingBytes > ($maximumProductBytes - $productBytes)
        ) {
            throw ValidationException::withMessages([
                'file' => 'The digital file quota for this product has been reached.',
            ]);
        }

        if (! $product->store_id) {
            return;
        }

        $storeBytes = (int) DB::table('product_files')
            ->join('products', 'products.id', '=', 'product_files.product_id')
            ->where('products.store_id', $product->store_id)
            ->sum('product_files.size');

        if ($incomingBytes > ($maximumStoreBytes - $storeBytes)) {
            throw ValidationException::withMessages([
                'file' => 'The digital file quota for this store has been reached.',
            ]);
        }
    }

    private function safeExtension(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = is_string($mime) ? (self::ALLOWED_MIME_TYPES[$mime] ?? null) : null;

        if ($extension === null) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }

        return $extension;
    }

    private function uploadDisk(): string
    {
        $disk = (string) config('products.digital_upload.disk', 'private');
        $allowed = (array) config('products.digital_upload.allowed_disks', ['private']);
        $configuration = config('filesystems.disks.'.$disk);

        if (! in_array($disk, $allowed, true) || ! is_array($configuration)) {
            throw new RuntimeException('The digital product upload disk is not allowlisted.');
        }

        if (
            ($configuration['visibility'] ?? 'private') === 'public'
            || (bool) ($configuration['serve'] ?? false)
            || ($configuration['throw'] ?? false) !== true
        ) {
            throw new RuntimeException('The digital product upload disk must be private and fail-fast.');
        }

        $driver = $configuration['driver'] ?? null;
        if (! in_array($driver, ['local', 's3'], true)) {
            throw new RuntimeException('The digital product upload disk driver is not supported.');
        }

        if ($driver === 'local') {
            $root = str_replace('\\', '/', rtrim((string) ($configuration['root'] ?? ''), '/\\'));
            $publicRoots = [
                str_replace('\\', '/', rtrim(public_path(), '/\\')),
                str_replace('\\', '/', rtrim(storage_path('app/public'), '/\\')),
            ];

            foreach ($publicRoots as $publicRoot) {
                if ($root === $publicRoot || str_starts_with($root, $publicRoot.'/')) {
                    throw new RuntimeException('The digital product upload disk root is publicly reachable.');
                }
            }
        }

        return $disk;
    }

    private function safeOriginalName(string $name): string
    {
        $trimmed = trim($name);

        if (
            $trimmed !== $name
            || $trimmed === ''
            || str_contains($trimmed, '/')
            || str_contains($trimmed, '\\')
            || in_array($trimmed, ['.', '..'], true)
            || preg_match('/[\x00-\x1F\x7F]/u', $trimmed) === 1
        ) {
            throw ValidationException::withMessages(['name' => 'The file name is invalid.']);
        }

        return Str::limit($trimmed, 255, '');
    }

    private function deleteIsolatedChunkDirectory(string $root, string $folder): void
    {
        $resolvedRoot = realpath($root);
        $resolvedParent = realpath(dirname($folder));
        $resolvedFolder = realpath($folder);
        $folderName = basename($folder);

        if (
            $resolvedRoot === false
            || $resolvedParent === false
            || $resolvedFolder === false
            || ! hash_equals($resolvedRoot, $resolvedParent)
            || ! hash_equals($resolvedRoot, dirname($resolvedFolder))
            || ! preg_match('/^[a-f0-9]{64}$/', $folderName)
        ) {
            throw new RuntimeException('Refusing to delete an unsafe chunk directory.');
        }

        File::deleteDirectory($folder);
    }
}
