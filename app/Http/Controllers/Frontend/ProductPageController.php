<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductContentSanitizer;
use Illuminate\Contracts\View\View;

class ProductPageController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->published()
            ->with(['primaryImage', 'primaryVariant', 'store', 'images' => function ($query) {
                $query->limit(2);
            }])
            ->latest()
            ->paginate(24);

        return view('frontend.pages.index', compact('products'));
    }

    public function show(string $slug, ProductContentSanitizer $contentSanitizer): View
    {
        $product = Product::query()
            ->published()
            ->where('slug', $slug)
            ->with(['images:id,path,product_id', 'store', 'categories', 'primaryVariant'])
            ->firstOrFail();
        $safeShortDescriptionHtml = $contentSanitizer->sanitize($product->short_description);
        $safeDescriptionHtml = $contentSanitizer->sanitize($product->description);
        $product->short_description = $safeShortDescriptionHtml;
        $product->description = $safeDescriptionHtml;

        return view('frontend.pages.show', compact(
            'product',
            'safeShortDescriptionHtml',
            'safeDescriptionHtml',
        ));
    }
}
