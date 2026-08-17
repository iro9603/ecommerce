<?php

namespace App\Http\Requests\Admin;

use App\Services\ProductContentSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProductStoreRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:100000'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'special_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', 'lte:price'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'stock_status' => ['required', 'in:in_stock,out_of_stock'],
            'status' => ['required', 'in:active,inactive,draft'],
            'store' => ['required', 'exists:stores,id'],
            'is_featured' => ['nullable'],
            'categories' => ['required', 'array'],
            'categories.*' => ['required', 'exists:categories,id'],
            'brand' => ['required', 'exists:brands,id'],
            'is_new' => ['nullable'],
            'is_hot' => ['nullable'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'exists:tags,id'],

        ];
    }
}
