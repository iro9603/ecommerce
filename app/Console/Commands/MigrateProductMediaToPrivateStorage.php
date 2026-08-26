<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductMediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MigrateProductMediaToPrivateStorage extends Command
{
    protected $signature = 'products:migrate-media-private {--dry-run}';

    protected $description = 'Move legacy ProductImage objects out of public/ into private controlled storage.';

    public function handle(ProductMediaStorageService $storage): int
    {
        $migrated = 0;
        $failed = 0;

        ProductImage::query()
            ->select(['id', 'product_id', 'path'])
            ->orderBy('id')
            ->chunkById(100, function ($images) use ($storage, &$migrated, &$failed): void {
                foreach ($images as $image) {
                    try {
                        if ($this->migrateImage($image, $storage)) {
                            $migrated++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        $this->error('ProductImage '.$image->getKey().': '.$exception->getMessage());
                    }
                }
            });

        $this->info("Migrated: {$migrated}; failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function migrateImage(
        ProductImage $coordinate,
        ProductMediaStorageService $storage,
    ): bool {
        $path = $storage->normalizePath((string) $coordinate->path);
        $publicPath = public_path($path);

        if (! File::isFile($publicPath)) {
            if ($storage->exists($path)) {
                return false;
            }

            throw new RuntimeException('Neither the legacy public object nor a private object exists.');
        }

        $storage->extensionForLocalFile($publicPath);

        if ($this->option('dry-run')) {
            $this->line('Would migrate ProductImage '.$coordinate->getKey().' ('.$path.').');

            return false;
        }

        $privateExisted = $storage->exists($path);

        $migrated = DB::transaction(function () use (
            $coordinate,
            $storage,
            $path,
            $publicPath,
            $privateExisted,
        ): bool {
            $product = Product::withTrashed()
                ->whereKey($coordinate->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $image = ProductImage::query()
                ->whereKey($coordinate->getKey())
                ->where('product_id', $product->getKey())
                ->where('path', $path)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $privateExisted) {
                $stream = fopen($publicPath, 'rb');

                if ($stream === false) {
                    throw new RuntimeException('Unable to open the legacy public object.');
                }

                try {
                    if (! Storage::disk($storage->disk())->put($path, $stream)) {
                        throw new RuntimeException('Unable to write the private media object.');
                    }
                } finally {
                    fclose($stream);
                }

                DB::connection()->afterRollBack(
                    fn () => Storage::disk($storage->disk())->delete($path),
                );
            }

            return $image->exists;
        }, 5);

        $this->deletePublicCopy($publicPath);

        return $migrated;
    }

    private function deletePublicCopy(string $publicPath): void
    {
        File::delete($publicPath);
        clearstatcache(true, $publicPath);

        if (File::exists($publicPath)) {
            throw new RuntimeException(
                'Unable to delete the legacy public product media object.'
            );
        }
    }
}
