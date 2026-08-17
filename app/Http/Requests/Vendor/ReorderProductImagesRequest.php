<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->can('create', Product::class) !== true) {
            return false;
        }

        $images = ProductImage::query()
            ->whereIn('id', $this->submittedImageIds())
            ->get(['id', 'product_id']);

        $products = Product::query()
            ->whereIn('id', $images->pluck('product_id')->unique())
            ->get()
            ->keyBy(fn (Product $product): int => (int) $product->getKey());

        foreach ($images as $image) {
            $product = $products->get((int) $image->product_id);

            if ($product === null || ! $user->can('reorderImages', $product)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:100'],
            'images.*' => ['required', 'array:id,order'],
            'images.*.id' => ['required', 'integer', 'min:1', 'distinct', 'exists:product_images,id'],
            'images.*.order' => ['required', 'integer', 'min:0', 'max:99', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $productCount = ProductImage::query()
                ->whereIn('id', $this->submittedImageIds())
                ->distinct()
                ->count('product_id');

            if ($productCount !== 1) {
                $validator->errors()->add(
                    'images',
                    'All reordered images must belong to the same product.',
                );
            }
        });
    }

    /**
     * @return list<int>
     */
    private function submittedImageIds(): array
    {
        if (! is_array($this->input('images'))) {
            return [];
        }

        $ids = [];

        foreach ($this->input('images') as $image) {
            if (! is_array($image)) {
                continue;
            }

            $id = filter_var(
                $image['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            if ($id !== false) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
