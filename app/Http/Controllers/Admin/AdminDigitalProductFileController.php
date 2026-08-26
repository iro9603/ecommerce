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
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 401);

        $product = Product::query()
            ->whereKey((int) $request->validated('product_id'))
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
            static fn (Product $lockedProduct) => abort_unless(
                $lockedProduct->product_type === 'digital',
                404,
            ),
            null,
            'Digital product file changed by administrator.',
        );

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
        $disk = (string) config('products.digital_upload.disk', 'private');

        $path = DB::transaction(function () use ($product, $file, $moderation): string {
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
            $path = (string) $lockedFile->path;
            abort_unless($this->isSafeProductPath($path), 422, 'Unsafe stored file path.');
            $lockedFile->delete();

            $moderation->markForReview(
                $lockedProduct,
                null,
                'Digital product file removed by administrator.',
            );

            return $path;
        }, 5);

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
