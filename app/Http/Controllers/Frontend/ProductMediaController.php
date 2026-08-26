<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductMediaStorageService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductMediaController extends Controller
{
    public function __invoke(
        ProductImage $image,
        ProductMediaStorageService $storage,
    ): StreamedResponse {
        $product = Product::withTrashed()
            ->whereKey($image->product_id)
            ->firstOrFail();

        abort_unless($this->mayView($product), 404);
        abort_unless($storage->exists((string) $image->path), 404);

        $stream = $storage->readStream((string) $image->path);
        $mime = $storage->mimeType((string) $image->path);

        return response()->stream(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="product-image"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function mayView(Product $product): bool
    {
        if (Product::query()->published()->whereKey($product->getKey())->exists()) {
            return true;
        }

        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin && $admin->can('Product Management')) {
            return true;
        }

        $user = Auth::guard('web')->user();

        return $user instanceof User
            && $product->store()
                ->withTrashed()
                ->where('seller_id', $user->getKey())
                ->exists();
    }
}
