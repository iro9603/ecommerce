<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\DigitalProductChunkUploadRequest;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\DigitalProductFileUploadService;
use App\Services\ProductModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class VendorDigitalProductFileController extends Controller
{
    public function store(
        DigitalProductChunkUploadRequest $request,
        DigitalProductFileUploadService $uploads,
        ProductModerationService $moderation,
    ): JsonResponse {
        $product = Product::query()
            ->whereKey((int) $request->validated('product_id'))
            ->firstOrFail();
        Gate::authorize('manageDigitalFiles', $product);

        $storedPath = null;
        $productFileId = null;

        try {
            $result = $uploads->storeChunk(
                $product,
                $request->file('file'),
                [
                    'uuid' => $request->chunkStorageKey(),
                    'index' => (int) $request->validated('dzchunkindex'),
                    'total_chunks' => (int) $request->validated('dztotalchunkcount'),
                    'total_size' => (int) $request->validated('dztotalfilesize'),
                    'original_name' => (string) $request->validated('name'),
                ],
                'vendor:'.$request->user()->getKey(),
            );

            if ($result['complete']) {
                $storedPath = (string) $result['product_file']->path;
                $productFileId = (int) $result['product_file']->getKey();

                DB::transaction(function () use (
                    $product,
                    $productFileId,
                    $moderation,
                    $request
                ): void {
                    $lockedProduct = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                    Gate::authorize('manageDigitalFiles', $lockedProduct);

                    ProductFile::query()
                        ->whereKey($productFileId)
                        ->where('product_id', $lockedProduct->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $moderation->markForReview(
                        $lockedProduct,
                        $request->user(),
                        'Digital product file changed by vendor.',
                    );
                });
            }
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk((string) config('products.digital_upload.disk', 'local'))->delete($storedPath);
            }

            if ($productFileId !== null) {
                ProductFile::query()->whereKey($productFileId)->delete();
            }

            throw $exception;
        }

        if (! $result['complete']) {
            return response()->json([
                'status' => 'chunk_received',
                'chunk' => $result['chunk'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'id' => $result['product_file']->getKey(),
        ]);
    }

    public function destroy(
        Request $request,
        Product $product,
        ProductFile $file,
        ProductModerationService $moderation,
    ): JsonResponse {
        $disk = (string) config('products.digital_upload.disk', 'local');

        $path = DB::transaction(function () use (
            $request,
            $product,
            $file,
            $moderation
        ): string {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::authorize('manageDigitalFiles', $lockedProduct);

            $lockedFile = ProductFile::query()
                ->whereKey($file->getKey())
                ->where('product_id', $lockedProduct->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $path = (string) $lockedFile->path;
            abort_unless($this->isSafeProductPath($path), 422, 'Unsafe stored file path.');
            $lockedFile->delete();

            $moderation->markForReview(
                $lockedProduct,
                $request->user(),
                'Digital product file removed by vendor.',
            );

            return $path;
        });

        Storage::disk($disk)->delete($path);

        return response()->json([
            'status' => 'success',
            'message' => 'File deleted successfully.',
        ]);
    }

    private function isSafeProductPath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! in_array('..', explode('/', $path), true)
            && (str_starts_with($path, 'uploads/') || str_starts_with($path, 'product-files/'));
    }
}
