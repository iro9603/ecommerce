<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DigitalProductChunkUploadRequest;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\DigitalProductFileUploadService;
use App\Services\ProductModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminDigitalProductFileController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:Product Management')];
    }

    public function store(
        DigitalProductChunkUploadRequest $request,
        DigitalProductFileUploadService $uploads,
        ProductModerationService $moderation,
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 401);

        $storedPath = null;

        try {
            $result = DB::transaction(function () use (
                $request,
                $uploads,
                $moderation,
                $admin,
                &$storedPath
            ): array {
                $product = Product::query()
                    ->whereKey((int) $request->validated('product_id'))
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($product->product_type === 'digital', 404);

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
                    'admin:'.$admin->getKey(),
                );

                if ($result['complete']) {
                    $storedPath = (string) $result['product_file']->path;
                    $moderation->markForReview(
                        $product,
                        null,
                        'Digital product file changed by administrator.',
                    );
                }

                return $result;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
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
        Product $product,
        ProductFile $file,
        ProductModerationService $moderation,
    ): JsonResponse {
        $disk = in_array($file->disk, ['local', 'public', 's3'], true) ? $file->disk : 'local';
        abort_unless($this->isSafeProductPath((string) $file->path), 422, 'Unsafe stored file path.');
        $path = (string) $file->path;

        DB::transaction(function () use ($product, $file, $moderation): void {
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedProduct->product_type === 'digital', 404);

            $lockedFile = ProductFile::query()
                ->whereKey($file->getKey())
                ->where('product_id', $lockedProduct->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedFile->delete();

            $moderation->markForReview(
                $lockedProduct,
                null,
                'Digital product file removed by administrator.',
            );
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
