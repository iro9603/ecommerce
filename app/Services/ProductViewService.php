<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductViewService
{
    public function __construct(
        protected ProductContentSanitizer $contentSanitizer,
    ) {}

    public function build(int|string $productId): array
    {
        $product = $this->getProduct($productId);

        $safeShortDescriptionHtml = $this->contentSanitizer
            ->sanitize($product->short_description);

        $safeDescriptionHtml = $this->contentSanitizer
            ->sanitize($product->description);

        $product->short_description = $safeShortDescriptionHtml;
        $product->description = $safeDescriptionHtml;

        $variantPayloads = $product->publicVariantPayloads();
        $attributeGroups = $product->groupedAttributeValues();
        $defaultVariant = $product->defaultVariant();

        $pricing = $defaultVariant && $defaultVariant->canPurchase()
            ? $defaultVariant->pricing()
            : ['regular_price' => null, 'special_price' => null, 'effective_price' => null, 'has_active_special' => false];

        $relatedProducts = $this->getRelatedProducts($product);

        return compact(
            'product',
            'safeShortDescriptionHtml',
            'safeDescriptionHtml',
            'variantPayloads',
            'attributeGroups',
            'defaultVariant',
            'pricing',
            'relatedProducts',
        );
    }

    protected function getProduct(int|string $productId): Product
    {
        return Product::query()
            ->published()
            ->whereKey($productId)
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
    }

    protected function getRelatedProducts(Product $product): Collection
    {
        $productCategoryIds = $product->categories->pluck('id');

        return Product::query()
            ->published()
            ->whereKeyNot($product->getKey())
            ->when(
                $productCategoryIds->isNotEmpty(),

                fn($query) => $query->whereHas(
                    'categories',
                    fn($categoryQuery) => $categoryQuery
                        ->whereIn('categories.id', $productCategoryIds),
                ),

                fn($query) => $query
                    ->where('store_id', $product->store_id),
            )
            ->with([
                'primaryImage',
                'primaryVariant',
                'store',

                'images' => fn($query) => $query->limit(2),
            ])
            ->latest('id')
            ->limit(6)
            ->get();
    }
}
