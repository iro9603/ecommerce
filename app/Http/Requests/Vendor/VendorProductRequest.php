<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use App\Services\ProductContentSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class VendorProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $sanitizer = app(ProductContentSanitizer::class);
        $slug = $this->input('slug') ?: $this->input('name');
        $shortDescription = $this->input('short_description');
        $content = $this->input('content');

        $this->merge([
            'slug' => is_string($slug) ? Str::slug($slug) : $slug,
            'short_description' => is_string($shortDescription)
                ? $sanitizer->sanitize($shortDescription)
                : $shortDescription,
            'content' => is_string($content) ? $sanitizer->sanitize($content) : $content,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function productRules(?int $ignoredProductId = null): array
    {
        $slugRule = Rule::unique('products', 'slug');

        if ($ignoredProductId !== null) {
            $slugRule->ignore($ignoredProductId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slugRule,
            ],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:100000'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'special_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
                'lte:price',
            ],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'manage_stock' => ['sometimes', 'accepted'],
            'quantity' => [
                Rule::requiredIf(fn (): bool => $this->has('manage_stock')),
                'nullable',
                'integer',
                'min:0',
                'max:2147483647',
            ],
            'stock_status' => ['required', Rule::in(['in_stock', 'out_of_stock'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'draft'])],
            'categories' => ['required', 'array', 'min:1', 'max:20'],
            'categories.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'brand' => ['required', 'integer', 'exists:brands,id'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],

            // Vendor-controlled requests must never accept ownership,
            // promotion, publication-review, or product-type decisions.
            'store' => ['prohibited'],
            'store_id' => ['prohibited'],
            'product_type' => ['prohibited'],
            'approved_status' => ['prohibited'],
            'is_featured' => ['prohibited'],
            'featured' => ['prohibited'],
            'is_hot' => ['prohibited'],
            'hot' => ['prohibited'],
            'is_new' => ['prohibited'],
            'new' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'moderation_reason' => ['prohibited'],
            'risk_level' => ['prohibited'],
            'risk_score' => ['prohibited'],
            'moderation_version' => ['prohibited'],
            'reviewed_version' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('quantity') && ! $this->has('manage_stock')) {
                $validator->errors()->add(
                    'quantity',
                    'Quantity may only be supplied when stock management is enabled.',
                );
            }

            if (
                $this->has('manage_stock')
                && is_numeric($this->input('quantity'))
                && (int) $this->input('quantity') === 0
                && $this->input('stock_status') === 'in_stock'
            ) {
                $validator->errors()->add(
                    'stock_status',
                    'A product with zero managed stock cannot be marked as in stock.',
                );
            }
        });
    }

    protected function routeProduct(): ?Product
    {
        $routeProduct = $this->route('product');

        if ($routeProduct instanceof Product) {
            return $routeProduct;
        }

        if (filter_var($routeProduct, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return null;
        }

        return Product::query()->find((int) $routeProduct);
    }
}
