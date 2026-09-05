<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductContentSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CartController extends Controller
{
    function index(): View
    {
        return view('frontend.pages.cart');
    }

    function addToCart(Request $request, ProductContentSanitizer $contentSanitizer)
    {
        $product = Product::query()
            ->published()
            ->where('id', $request->product_id)
            ->with([
                'images:id,path,product_id,order',
                'store',
                'categories',
                'tags',
                'attributeAssignments.attribute',
                'attributeAssignments.value',
                'variants' => fn($query) => $query
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->orderBy('id'),
                'variants.attributeValues',
            ])
            ->firstOrFail();

        $safeShortDescriptionHtml = $contentSanitizer->sanitize($product->short_description);
        $safeDescriptionHtml = $contentSanitizer->sanitize($product->description);
        $product->short_description = $safeShortDescriptionHtml;
        $product->description = $safeDescriptionHtml;

        $variantPayloads = $product->publicVariantPayloads();
        $attributeGroups = $product->groupedAttributeValues();
        $defaultVariant = $product->defaultVariant();
        $pricing = $defaultVariant?->pricing() ?? $product->pricing();

        $productCategoryIds = $product->categories->pluck('id');
        $relatedProducts = Product::query()
            ->published()
            ->where('id', '!=', $product->getKey())
            ->when(
                $productCategoryIds->isNotEmpty(),
                fn($query) => $query->whereHas(
                    'categories',
                    fn($categoryQuery) => $categoryQuery->whereIn('categories.id', $productCategoryIds),
                ),
                fn($query) => $query->where('store_id', $product->store_id),
            )
            ->with(['primaryImage', 'primaryVariant', 'store', 'images' => function ($query) {
                $query->limit(2);
            }])
            ->latest('id')
            ->limit(6)
            ->get();
        $modal = view('components.frontend.product-quick-view-modal', compact(
            'product',
            'safeShortDescriptionHtml',
            'safeDescriptionHtml',
            'variantPayloads',
            'attributeGroups',
            'defaultVariant',
            'pricing',
            'relatedProducts',
        ))->render();

        return response()->json([
            'status' => 'success',
            'modal' => $modal
        ]);
    }
}
