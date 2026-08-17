<?php

namespace App\Http\Requests\Vendor;

class ProductUpdateRequest extends VendorProductRequest
{
    public function authorize(): bool
    {
        $product = $this->routeProduct();

        return $product !== null
            && $this->user()?->can('update', $product) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->productRules($this->routeProduct()?->getKey());
    }
}
