<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductCatalogController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->published()
            ->with(['primaryImage', 'primaryVariant', 'store'])
            ->latest()
            ->paginate(24);

        return view('frontend.product.index', compact('products'));
    }

    public function show(string $slug): View
    {
        $product = Product::query()
            ->published()
            ->where('slug', $slug)
            ->with(['images', 'store', 'categories', 'primaryVariant'])
            ->firstOrFail();

        return view('frontend.product.show', compact('product'));
    }
}
