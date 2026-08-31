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
            ->with([
                'images:id,path,product_id,order',
                'store',
                'categories',
                'tags',
                'attributeAssignments.attribute',
                'attributeAssignments.value',
                'variants' => fn ($query) => $query
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
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', $productCategoryIds),
                ),
                fn ($query) => $query->where('store_id', $product->store_id),
            )
            ->with(['primaryImage', 'primaryVariant', 'store', 'images' => function ($query) {
                $query->limit(2);
            }])
            ->latest('id')
            ->limit(6)
            ->get();

        return view('frontend.pages.show', compact(
            'product',
            'safeShortDescriptionHtml',
            'safeDescriptionHtml',
            'variantPayloads',
            'attributeGroups',
            'defaultVariant',
            'pricing',
            'relatedProducts',
        ));
    }
}
