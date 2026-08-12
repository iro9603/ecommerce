<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tag;
use App\Services\AlertService;
use App\Traits\FileUploadTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller implements HasMiddleware
{
    use FileUploadTrait;

    /* static function Middleware(): array
    {
        return [
            new Middleware('permission:Product Management')

        ];
    }
 */
    function index(): View
    {
        $products = Product::orderBy('created_at', 'desc')->paginate(30);
        return view('admin.product.index', compact('products'));
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
        $product = DB::transaction(function () use ($request, $type) {

            if (!in_array($type, ['physical', 'digital'])) {
                abort(404);
            }

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
            $product->in_stock = $request->stock_status == 'in_stock' ? 1 : 0;
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

            return $product;
        });

        if ($type == 'physical') {
            return response()->json([
                'id' => $product->id,
                'status' => 'success',
                'redirect_url' => route('admin.products.edit', $product->id) . '#product-images',
                'message' => 'Product created successfully.'
            ]);
        } else {
            return response()->json([
                'id' => $product->id,
                'status' => 'success',
                'redirect_url' => route('admin.digital-products.edit', $product->id) . '#product-images',
                'message' => 'Product created successfully.'
            ]);
        }
    }

    function edit(int $id)
    {
        $product = Product::findOrFail($id);
        $productCategoryIds = $product->categories->pluck('id')->toArray();
        $productTagIds = $product->tags->pluck('id')->toArray();
        $stores = Store::select(['name', 'id'])->get();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        $attributesWithValues = $product->attributeWithValues ?? [];
        $variants = $product?->variants ?? [];
        return view('admin.product.edit', compact('stores', 'brands', 'tags', 'categories', 'product', 'productCategoryIds', 'productTagIds', 'attributesWithValues', 'variants'));
    }

    function editDigital(int $id)
    {
        $product = Product::findOrFail($id);
        if ($product->product_type != 'digital') {
            abort(404);
        }
        $productCategoryIds = $product->categories->pluck('id')->toArray();
        $productTagIds = $product->tags->pluck('id')->toArray();
        $stores = Store::select(['name', 'id'])->get();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        return view('admin.product.digital-edit', compact('stores', 'brands', 'tags', 'categories', 'product', 'productCategoryIds', 'productTagIds'));
    }

    public function uploadDigitalProductFile(Request $request)
    {
        $file = $request->file('file');

        $chunkIndex = (int) $request->dzchunkindex;
        $totalChunks = (int) $request->dztotalchunkcount;
        $fileName = basename($request->name);

        $chunkFolder = storage_path('app/private/chunks/' . $fileName);

        if (!File::exists($chunkFolder)) {
            File::makeDirectory($chunkFolder, 0777, true);
        }

        $chunkPath = $chunkFolder . '/' . $chunkIndex;

        file_put_contents(
            $chunkPath,
            file_get_contents($file->getRealPath())
        );

        /*
     * No confiar en que chunkIndex == totalChunks - 1
     * significa que todos los demás ya llegaron.
     */
        $uploadedChunks = glob($chunkFolder . '/*');

        if (count($uploadedChunks) < $totalChunks) {
            return response()->json([
                'status' => 'chunk_received',
                'chunk' => $chunkIndex,
            ]);
        }

        // Todos los chunks están presentes.
        $uploadsFolder = storage_path('app/private/uploads');

        if (!File::exists($uploadsFolder)) {
            File::makeDirectory($uploadsFolder, 0777, true);
        }

        $extension = $file->getClientOriginalExtension();

        $storedFileName = \Str::uuid() . '.' . $extension;

        $relativePath = 'uploads/' . $storedFileName;

        $finalPath = storage_path(
            'app/private/' . $relativePath
        );

        $output = fopen($finalPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = $chunkFolder . '/' . $i;

            if (!File::exists($chunkFile)) {
                fclose($output);

                return response()->json([
                    'status' => 'error',
                    'message' => "Missing chunk {$i}",
                ], 422);
            }

            $input = fopen($chunkFile, 'rb');

            stream_copy_to_stream($input, $output);

            fclose($input);
        }

        fclose($output);

        // Elimina chunks + carpeta.
        File::deleteDirectory($chunkFolder);

        $validationResponse = $this->validateFinalFile($finalPath);
        if ($validationResponse !== true) {
            unlink($finalPath);
            return $validationResponse;
        }

        $relativePath = 'uploads/' . $storedFileName;

        $productFile = new ProductFile();
        $productFile->product_id = $request->product_id;
        $productFile->filename = $fileName;
        $productFile->path = $relativePath;
        $productFile->extension = $extension;
        $productFile->size = filesize($finalPath);
        $productFile->save();

        return response()->json([
            'status' => 'success',
        ]);
    }

    function validateFinalFile(string $finalPath)
    {
        $maxSizeMb = 1000;
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;

        if (!file_exists($finalPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found',
            ], 404);
        }

        if (filesize($finalPath) > $maxSizeBytes) {
            return response()->json([
                'status' => 'error',
                'message' => 'File size limit exceeded',
            ], 413);
        }

        // MIME validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $finalPath);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'images' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/avif',
                'image/bmp',
                'image/tiff',
            ],

            'documents' => [
                'application/pdf',
                'text/plain',
                'text/csv',
                'application/rtf',
            ],

            'microsoft_office' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],

            'open_document' => [
                'application/vnd.oasis.opendocument.text',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.presentation',
            ],

            'ebooks' => [
                'application/epub+zip',
            ],

            'audio' => [
                'audio/mpeg',
                'audio/wav',
                'audio/ogg',
                'audio/flac',
                'audio/mp4',
                'audio/aac',
            ],

            'video' => [
                'video/mp4',
                'video/webm',
                'video/ogg',
                'video/quicktime',
            ],

            'archives' => [
                'application/x-zip-compressed',
                'application/zip',
                'application/x-7z-compressed',
            ],
        ];

        $allowedMimeTypesFlat = array_merge(...array_values($allowedMimeTypes));

        if (!in_array($mimeType, $allowedMimeTypesFlat, true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Invalid file type: {$mimeType}",
            ], 400);
        }

        return true;
    }

    function destroyDigitalProductFile(int $productId, int $id)
    {
        try {
            $productFile = ProductFile::where('id', $id)->where('product_id', $productId)->first();
            //delete from storage
            if (Storage::disk('local')->exists($productFile->path)) {
                Storage::disk('local')->delete($productFile->path);
            }
            $productFile->delete();
            return response()->json(['status' => 'success', 'message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            logger('Failed to delete file: ' . $e);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    function uploadImages(Request $request, Product $product)
    {

        $request->validate([
            'image' => ['required', 'image', 'max:3048']
        ]);

        $filePath = $this->uploadFile($request->file('image'));

        $productImage = new ProductImage();
        $productImage->product_id = $product->id;
        $productImage->path = $filePath;
        $productImage->order = ProductImage::where('product_id', $product->id)->max('order') + $product->id;
        $productImage->save();

        return response()->json([
            'status' => 'success',
            'id' => $productImage->id,
            'path' => asset($filePath),
            'message' => 'Image uploaded successfully.'
        ]);
    }

    function destroyImage(int $id)
    {
        $image = ProductImage::findOrFail($id);
        $this->deleteFile($image->path);
        $image->delete();
        return response()->json(['status' => 'success', 'message' => 'Image deleted successfully.']);
    }

    function imagesReorder(Request $request)
    {
        foreach ($request->images as $image) {
            ProductImage::where('id', $image['id'])->update(['order' => $image['order']]);
        }
    }

    function update(ProductUpdateRequest $request, int $id)
    {
        $product = Product::findOrFail($id);
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
        $product->in_stock = $request->stock_status == 'in_stock' ? 1 : 0;
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

        AlertService::created();

        return response()->json([
            'id' => $product->id,
            'status' => 'success',
            'message' => 'Product updated successfully.',
            'redirect_url' => route('admin.products.index')
        ]);
    }

    function storeAttributes(Request $request, Product $product)
    {
        $validated = $request->validate([
            'attribute_id' => ['nullable', 'integer'],
            'attribute_name' => ['required', 'string', 'max:255'],
            'attribute_type' => ['required', 'string', 'in:text,color'],
            'label' => ['required', 'array', 'min:1'],
            'label.*' => ['required', 'string', 'max:255'],
            'value_id' => ['nullable', 'array'],
            'value_id.*' => ['nullable', 'integer', 'distinct'],
            'color_value' => ['nullable', 'array'],
            'color_value.*' => ['nullable', 'string', 'max:32'],
        ]);

        $result = DB::transaction(function () use ($validated, $request, $product) {
            $attributeId = $validated['attribute_id'] ?? null;
            $isUpdate = filled($attributeId);

            if ($isUpdate) {
                $belongsToProduct = DB::table('product_attribute_values')
                    ->where('product_id', $product->id)
                    ->where('attribute_id', $attributeId)
                    ->exists();

                abort_unless($belongsToProduct, 404);
                $attribute = Attribute::findOrFail($attributeId);
            } else {
                $attribute = new Attribute();
            }

            $attribute->name = $validated['attribute_name'];
            $attribute->type = $validated['attribute_type'];
            $attribute->save();

            $values = $this->syncAttributeValues($attribute, $request, $product);
            $this->regenerateProductVariants($product);

            return compact('attribute', 'values', 'isUpdate');
        });
        $variantsHtml = $this->renderProductVariants($product);


        return response()->json([
            'status' => 'success',
            'attribute' => $result['attribute'],
            'values' => $result['values'],
            'variants_html' => $variantsHtml,
            'has_attributes' => $product->attributes()->exists(),
            'message' => $result['isUpdate']
                ? 'Attribute updated successfully.'
                : 'Attribute created successfully.'
        ]);
    }

    private function renderProductVariants(Product $product): string
    {
        $variants = $product->variants()
            ->orderBy('id')
            ->get();

        return view('admin.product.partials.variants', compact('variants'))->render();
    }

    function syncAttributeValues(Attribute $attribute, Request $request, Product $product): array
    {
        $labels = $request->input('label', []);
        $valueIds = $request->input('value_id', []);
        $colors = $request->input('color_value', []);
        $savedValues = [];

        foreach ($labels as $index => $label) {
            $valueId = $valueIds[$index] ?? null;

            if ($valueId) {
                $belongsToProduct = DB::table('product_attribute_values')
                    ->where('product_id', $product->id)
                    ->where('attribute_id', $attribute->id)
                    ->where('attribute_value_id', $valueId)
                    ->exists();

                abort_unless($belongsToProduct, 404);

                $attributeValue = AttributeValue::query()
                    ->whereKey($valueId)
                    ->where('attribute_id', $attribute->id)
                    ->firstOrFail();
            } else {
                $attributeValue = new AttributeValue();
                $attributeValue->attribute_id = $attribute->id;
            }

            $attributeValue->value = $label;
            $attributeValue->color = $attribute->type === 'color'
                ? ($colors[$index] ?? '#000000')
                : null;
            $attributeValue->save();

            DB::table('product_attribute_values')->updateOrInsert([
                'product_id' => $product->id,
                'attribute_id' => $attribute->id,
                'attribute_value_id' => $attributeValue->id
            ]);

            $savedValues[] = $attributeValue;
        }

        $savedValueIds = collect($savedValues)->pluck('id');
        $removedValueIds = DB::table('product_attribute_values')
            ->where('product_id', $product->id)
            ->where('attribute_id', $attribute->id)
            ->pluck('attribute_value_id')
            ->diff($savedValueIds);

        if ($removedValueIds->isNotEmpty()) {
            DB::table('product_attribute_values')
                ->where('product_id', $product->id)
                ->where('attribute_id', $attribute->id)
                ->whereIn('attribute_value_id', $removedValueIds)
                ->delete();

            AttributeValue::query()
                ->whereIn('id', $removedValueIds)
                ->whereNotIn('id', DB::table('product_attribute_values')->select('attribute_value_id'))
                ->delete();
        }

        return $savedValues;
    }

    public function destroyAttribute(Product $product, Attribute $attribute)
    {
        return DB::transaction(function () use ($product, $attribute) {
            // Elimina todos los valores asociados en la tabla pivot para este producto y atributo
            $belongsToProduct = DB::table('product_attribute_values')
                ->where('product_id', $product->id)
                ->where('attribute_id', $attribute->id)
                ->exists();

            abort_unless($belongsToProduct, 404);

            DB::table('product_attribute_values')
                ->where('product_id', $product->id)
                ->where('attribute_id', $attribute->id)
                ->delete();

            // (Opcional) Si el atributo ya no está asociado a ningún producto, elimínalo por completo
            $isUsedElsewhere = DB::table('product_attribute_values')
                ->where('attribute_id', $attribute->id)
                ->exists();

            if (!$isUsedElsewhere) {
                // Primero borra sus valores
                AttributeValue::where('attribute_id', $attribute->id)->delete();
                // Luego borra el atributo
                $attribute->delete();
            }

            $this->regenerateProductVariants($product);
            $variantsHtml = $this->renderProductVariants($product);

            return response()->json([
                'status'  => 'success',
                'message' => 'Attribute removed successfully.',
                'variants_html' => $variantsHtml,
                'has_attributes' => $product->attributes()->exists(),

            ]);
        });
    }

    function regenerateProductVariants(Product $product)
    {
        // clear existing variants
        $this->clearExistingVariants($product);

        // get current attribute values group by attributes
        $attributeGroups = $this->getAttributeGroups($product);

        if ($attributeGroups->isEmpty()) {
            return;
        }

        $combinations = $this->cartesianProduct($attributeGroups);

        $this->createVariantsFromCombinations($product, $combinations);
    }

    public function updateVariants(Request $request, Product $product)
    {
        $variantId = $request->integer('variant_id');

        $request->merge([
            'variant_stock_status' => $request->input(
                "variant_stock_status_{$variantId}",
                $request->input('variant_stock_status')
            ),

            // Un checkbox desmarcado no llega en la petición.
            'variant_manage_stock' => $request->boolean('variant_manage_stock'),
            'variant_is_default' => $request->boolean('variant_is_default'),
            'variant_is_active' => $request->boolean('variant_is_active'),
        ]);

        $validated = $request->validate([
            'variant_id' => [
                'required',
                'integer',
            ],
            'variant_sku' => [
                'nullable',
                'string',
                'max:255',
            ],
            'variant_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'variant_special_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'variant_manage_stock' => [
                'required',
                'boolean',
            ],
            'variant_quantity' => [
                'nullable',
                'integer',
                'min:0',
                'required_if:variant_manage_stock,1',
            ],
            'variant_stock_status' => [
                'required',
                'in:in_stock,out_of_stock',
            ],
            'variant_is_default' => [
                'required',
                'boolean',
            ],
            'variant_is_active' => [
                'required',
                'boolean',
            ],
        ]);

        // Garantiza que la variante pertenezca al producto actual.
        $variant = $product->variants()
            ->whereKey($validated['variant_id'])
            ->firstOrFail();

        $variant->sku = $validated['variant_sku'] ?? null;
        $variant->price = $validated['variant_price'];
        $variant->special_price = $validated['variant_special_price'] ?? null;
        $variant->manage_stock = $validated['variant_manage_stock'];

        $variant->qty = $validated['variant_manage_stock']
            ? ($validated['variant_quantity'] ?? 0)
            : null;

        $variant->in_stock =
            $validated['variant_stock_status'] === 'in_stock';

        $variant->is_default = $validated['variant_is_default'];
        $variant->is_active = $validated['variant_is_active'];

        $variant->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Variant updated successfully',
            'variant' => [
                'id' => $variant->id,
                'manage_stock' => $variant->manage_stock,
                'quantity' => $variant->qty,
                'in_stock' => $variant->in_stock,
                'is_default' => $variant->is_default,
                'is_active' => $variant->is_active,
            ],
        ]);
    }

    function clearExistingVariants(Product $product)
    {
        foreach ($product->variants as $variant) {
            DB::table('product_variant_attribute_value')
                ->where('product_variant_id', $variant->id)
                ->delete();

            $variant->delete();
        }
    }

    function getAttributeGroups(Product $product)
    {
        $groupedAttributes = DB::table('product_attribute_values')
            ->where('product_id', $product->id)
            ->get()->groupBy('attribute_id');

        $attributeGroups = collect();

        foreach ($groupedAttributes as $attributeId => $items) {
            $attributeValues = AttributeValue::whereIn('id', $items->pluck('attribute_value_id'))->get();
            $attributeGroups->push($attributeValues);
        }


        return $attributeGroups;
    }

    function cartesianProduct(Collection $attributeGroups)
    {
        $result = [[]];
        foreach ($attributeGroups as $attributeValues) {
            $temp = [];
            foreach ($result as $resultItem) {
                foreach ($attributeValues as $attributeValue) {
                    $temp[] = array_merge($resultItem, [$attributeValue]);
                }
            }

            $result = $temp;
        }

        return $result;
    }

    function createVariantsFromCombinations(Product $product, array $combinations)
    {
        foreach ($combinations as $combination) {
            $variant = $this->createSingleVariant($product, $combination);
            $this->attachAttributesToVariant($variant, $combination);
        }
    }

    function createSingleVariant(Product $product, array $combination)
    {
        $variantName = collect($combination)->pluck('value')->implode('/');
        return ProductVariant::create([
            'product_id' => $product->id,
            'name' => $variantName,
            'price' => 0,
            'sku' => '',
            'qty' => 0
        ]);
    }

    function attachAttributesToVariant(ProductVariant $variant, array $combination)
    {
        foreach ($combination as $attributeValue) {
            DB::table('product_variant_attribute_value')->insert([
                'product_variant_id' => $variant->id,
                'attribute_id' => $attributeValue->attribute_id,
                'attribute_value_id' => $attributeValue->id
            ]);
        }
    }

    function destroy(Product $product)
    {
        if (Auth::user()->hasRole('Super Admin') || hasPermission(['Product Management'])) {
            $product->delete();
            notyf()->success('Product deleted successfully');
            return response()->json(['status' => 'success', 'message' => 'Product deleted successfully']);
        }

        notyf()->error('You do not have permission to delete this product');
        return response()->json(['status' => 'error', 'message' => 'You do not have permission to delete this product']);
    }
}
