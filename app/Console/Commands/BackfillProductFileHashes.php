<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Store;
use App\Services\DigitalProductFileUploadService;
use App\Services\ProductModerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackfillProductFileHashes extends Command
{
    protected $signature = 'products:backfill-file-hashes {--batch=100}';

    protected $description = 'Backfill missing SHA-256 hashes and remoderate affected digital products';

    public function handle(
        DigitalProductFileUploadService $uploads,
        ProductModerationService $moderation,
    ): int {
        $batch = max(1, (int) $this->option('batch'));
        $disk = $uploads->disk();
        $updated = 0;

        Product::withTrashed()
            ->whereHas('files', fn ($query) => $query->whereNull('sha256'))
            ->orderBy('id')
            ->chunkById($batch, function ($products) use ($disk, $moderation, &$updated): void {
                foreach ($products as $product) {
                    $updated += $this->backfillProduct(
                        (int) $product->getKey(),
                        $disk,
                        $moderation,
                    );
                }
            });

        $this->info('Updated '.$updated.' digital product file hashes.');

        return self::SUCCESS;
    }

    private function backfillProduct(
        int $productId,
        string $disk,
        ProductModerationService $moderation,
    ): int {
        $storeId = Product::withTrashed()->whereKey($productId)->value('store_id');

        if ($storeId === null) {
            return 0;
        }

        return DB::transaction(function () use ($productId, $storeId, $disk, $moderation): int {
            Store::withTrashed()->whereKey((int) $storeId)->lockForUpdate()->firstOrFail();
            $product = Product::withTrashed()->whereKey($productId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $product->store_id === (int) $storeId, 409);
            $files = ProductFile::query()
                ->where('product_id', $productId)
                ->whereNull('sha256')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($files->isEmpty()) {
                return 0;
            }

            $updated = 0;

            foreach ($files as $file) {
                try {
                    $stream = Storage::disk($disk)->readStream($file->path);

                    if (! is_resource($stream)) {
                        throw new RuntimeException('Unable to open a read stream.');
                    }

                    try {
                        $context = hash_init('sha256');
                        $bytes = hash_update_stream($context, $stream);

                        if ($bytes === false) {
                            throw new RuntimeException('Unable to hash the file stream.');
                        }

                        $sha256 = hash_final($context);
                    } finally {
                        fclose($stream);
                    }

                    $file->forceFill([
                        'sha256' => $sha256,
                        'size' => Storage::disk($disk)->size($file->path),
                    ])->save();
                    $updated++;
                } catch (\Throwable $exception) {
                    $this->warn(
                        'Failed to hash file '.$file->path.': '.$exception->getMessage(),
                    );
                }
            }

            $moderation->markForReview(
                $product,
                null,
                'Digital file integrity metadata was backfilled.',
            );

            return $updated;
        }, 5);
    }
}
