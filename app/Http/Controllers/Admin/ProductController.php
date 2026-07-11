<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tag;
use App\Services\AlertService;
use App\Traits\FileUploadTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use FileUploadTrait;

    function index(): View
    {
        return view('admin.product.index');
    }

    function create(): View
    {
        $stores = Store::select(['name', 'id'])->get();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();
        return view('admin.product.create', compact('stores', 'brands', 'tags', 'categories'));
    }

    function store(ProductStoreRequest $request, string $type)
    {
        $product = new Product();
        $product->product_type = $type;
        $product->name = $request->name;
        $product->slug = $request->slug;
        $product->short_description = $request->short_description;
        $product->sku = $request->sku;
        $product->description = $request->content;
        $product->price = $request->price;
        $product->special_price = $request->special_price;
        $product->special_price_start = $request->from_date;
        $product->special_price_end = $request->to_date;
        $product->qty = $request->quantity;
        $product->manage_stock = $request->has('manage_stock') ? 'yes' : 'no';
        $product->in_stock = $request->stock_status == "in_stock" ? 1 : 0;
        $product->status = $request->status;
        $product->brand_id = $request->brand;
        $product->store_id = $request->store;
        $product->is_featured = $request->has('is_featured') ? 1 : 0;
        $product->is_hot = $request->has('is_hot') ? 1 : 0;
        $product->is_new = $request->has('is_new') ? 1 : 0;
        $product->save();

        /** Attach categories */
        $product->categories()->sync($request->categories);

        /** Attach tags */
        $product->tags()->sync($request->tags);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully.'
        ]);
    }

    function uploadImages(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:3048']
        ]);

        $filePath = $this->uploadFile($request->file('image'));

        return response()->json([
            'status' => 'success',
            'path' => asset($filePath),
            'message' => 'Image uploaded successfully.'
        ]);
    }
}
