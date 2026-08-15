<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'special_price' => ['nullable', 'numeric'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'quantity' => ['nullable', 'numeric'],
            'stock_status' => ['required', 'in:in_stock,out_of_stock'],
            'status' => ['required', 'in:active,inactive,draft'],
            'approved_status' => ['required', 'in:pending,approved,rejected'],
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
