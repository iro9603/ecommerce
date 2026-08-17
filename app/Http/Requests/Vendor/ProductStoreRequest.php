<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;

class ProductStoreRequest extends VendorProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
