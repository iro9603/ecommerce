<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Services\ProductViewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        protected ProductViewService $productViewService
    ) {}

    public function index(): View
    {
        return view('frontend.pages.cart');
    }

    protected function productModal(Product $product): string
    {
        $data = $this->productViewService->build($product->getKey());

        return view(
            'components.frontend.product-quick-view-modal',
            $data
        )->render();
    }

    public function addToCart(Request $request)
    {
        if (! user()) {
            throw ValidationException::withMessages([
                'message' => 'Please login to add product to cart',
            ]);
        }
        $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $data = $this->productViewService->build($request->integer('product_id'));

        /** @var Product $product */
        $product = $data['product'];

        $hasVariant = $product->variants->isNotEmpty();
        $quantity = max(1, $request->integer('quantity', 1));

        if ($hasVariant && ! $request->filled('variant_id')) {
            return response()->json([
                'status' => 'success',
                'modal' => view('components.frontend.product-quick-view-modal', $data)->render(),
                'has_variant' => true,
            ]);
        }

        if ($hasVariant) {
            $variant = $product->variants->firstWhere('id', $request->integer('variant_id'));

            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'La variante seleccionada no es válida.',
                ]);
            }

            if (! $variant->inStock()) {
                throw ValidationException::withMessages([
                    'message' => 'Product out of stock',
                ]);
            }

            if ($variant->effectivePrice() === null) {
                throw ValidationException::withMessages([
                    'message' => 'Product price is unavailable',
                ]);
            }

            if ($variant->managesStock() && $variant->stockQuantity() < $quantity) {
                throw ValidationException::withMessages([
                    'message' => 'Not enough product in stock. Available ' . $variant->stockQuantity(),
                ]);
            }

            if (Cart::where('user_id', user()->id)->where(['product_id' => $product->id])->where('variant_id', $request->variant_id)->exists()) {
                throw ValidationException::withMessages([
                    'message' => 'Product already added to cart',
                ]);
            }
        } else {
            if (! $product->inStock()) {
                throw ValidationException::withMessages([
                    'message' => 'Product out of stock',
                ]);
            }

            if ($product->effectivePrice() === null) {
                throw ValidationException::withMessages([
                    'message' => 'Product price is unavailable',
                ]);
            }

            if ($product->managesStock() && $product->stockQuantity() < $quantity) {
                throw ValidationException::withMessages([
                    'message' => 'Not enough product in stock. Available ' . $product->stockQuantity(),
                ]);
            }

            if (Cart::where('user_id', user()->id)->where(['product_id' => $product->id])->exists()) {
                throw ValidationException::withMessages([
                    'message' => 'Product already added to cart',
                ]);
            }
        }

        $this->store($request, $product, $hasVariant);

        return response()->json([
            'status' => 'success',
            'message' => 'Product added to cart successfully.',
            'has_variant' => false,
        ]);
    }

    public function store(Request $request, Product $product, bool $hasVariant)
    {
        $cart = new Cart;
        $cart->user_id = user()->id;
        $cart->product_id = $product->id;
        $cart->variant_id = $hasVariant
            ? $request->integer('variant_id')
            : null;
        $cart->name = $product->name;
        $cart->quantity = max(1, $request->integer('quantity', 1));
        $cart->save();
    }
}
