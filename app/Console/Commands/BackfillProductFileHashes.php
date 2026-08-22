<?php

namespace App\Console\Commands;

use App\Models\ProductFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillProductFileHashes extends Command
{
    protected $signature = 'products:backfill-file-hashes {--batch=100}';

    protected $description = 'Backfill missing SHA-256 hashes for existing digital product files';

    public function handle(): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $disk = (string) config('products.digital_upload.disk', 'local');
        $updated = 0;

        ProductFile::query()
            ->whereNull('sha256')
            ->orderBy('id')
            ->chunkById($batch, function ($files) use ($disk, &$updated): void {
                foreach ($files as $file) {
                    try {
                        $contents = Storage::disk($disk)->get($file->path);

                        if ($contents === null) {
                            $this->warn("Unable to read file {$file->path}.");

                            continue;
                        }

                        $file->forceFill(['sha256' => hash('sha256', $contents)])->save();
                        $updated++;
                    } catch (\Throwable $exception) {
                        $this->warn("Failed to hash file {$file->path}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Updated {$updated} digital product file hashes.");

        return self::SUCCESS;
    }
}
