<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tag;
use App\Services\AlertService;
use App\Services\ProductContentSanitizer;
use App\Services\ProductModerationService;
use App\Traits\FileUploadTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller implements HasMiddleware
{
    use FileUploadTrait;

    public static function Middleware(): array
    {
        return [
            new Middleware('permission:Product Management'),

        ];
    }

    public function index(): View
    {
        $products = Product::orderBy('created_at', 'desc')->paginate(30);

        return view('admin.product.index', compact('products'));
    }

    public function create(): View
    {
        $stores = Store::select(['name', 'id'])->get();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        return view('admin.product.create', compact('stores', 'brands', 'tags', 'categories'));
    }

    public function store(
        ProductStoreRequest $request,
        string $type,
        ProductModerationService $moderation
    ) {
        $reviewer = Auth::guard('admin')->user();
        abort_unless($reviewer instanceof Admin, 401);

        $product = DB::transaction(function () use ($request, $type, $moderation, $reviewer) {

            if (! in_array($type, ['physical', 'digital'])) {
                abort(404);
            }

            $product = new Product;
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

            $review = $moderation->approve(
                $product,
                $reviewer,
                'Product created and approved by an administrator.',
                (int) $product->moderation_version
            );

            abort_if($review === null, 409, 'The product changed while it was being approved.');

            return $product;
        });

        if ($type == 'physical') {
            return response()->json([
                'id' => $product->id,
                'status' => 'success',
                'redirect_url' => route('admin.products.edit', $product->id).'#product-images',
                'message' => 'Product created successfully.',
            ]);
        } else {
            return response()->json([
                'id' => $product->id,
                'status' => 'success',
                'redirect_url' => route('admin.digital-products.edit', $product->id).'#product-images',
                'message' => 'Product created successfully.',
            ]);
        }
    }

    public function edit(int $id, ProductContentSanitizer $sanitizer)
    {
        $product = Product::findOrFail($id);
        $this->sanitizeContentForEditing($product, $sanitizer);
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

    public function editDigital(int $id, ProductContentSanitizer $sanitizer)
    {
        $product = Product::findOrFail($id);
        if ($product->product_type != 'digital') {
            abort(404);
        }
        $this->sanitizeContentForEditing($product, $sanitizer);
        $productCategoryIds = $product->categories->pluck('id')->toArray();
        $productTagIds = $product->tags->pluck('id')->toArray();
        $stores = Store::select(['name', 'id'])->get();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        return view('admin.product.digital-edit', compact('stores', 'brands', 'tags', 'categories', 'product', 'productCategoryIds', 'productTagIds'));
    }

    private function sanitizeContentForEditing(
        Product $product,
        ProductContentSanitizer $sanitizer
    ): void {
        $product->short_description = $sanitizer->sanitize($product->short_description);
        $product->description = $sanitizer->sanitize($product->description);
    }

    private function recordAdminMaterialChange(
        Product $product,
        ProductModerationService $moderation,
        string $reason
    ): void {
        $moderation->markForReview($product, null, $reason);

        $reviewer = Auth::guard('admin')->user();

        if (! $reviewer instanceof Admin) {
            return;
        }

        $moderation->approve(
            $product,
            $reviewer,
            $reason,
            (int) $product->moderation_version
        );
    }

    public function uploadImages(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ) {

        $request->validate([
            'image' => ['required', 'image', 'max:3048'],
        ]);

        $filePath = $this->uploadFile($request->file('image'));
        abort_if($filePath === null, 422, 'The image could not be stored.');

        try {
            $productImage = DB::transaction(function () use ($product, $filePath, $moderation) {
                $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                $productImage = new ProductImage;
                $productImage->product_id = $product->id;
                $productImage->path = $filePath;
                $productImage->order = ((int) ProductImage::query()
                    ->where('product_id', $product->id)
                    ->max('order')) + 1;
                $productImage->save();

                $this->recordAdminMaterialChange(
                    $product,
                    $moderation,
                    'Product image uploaded by an administrator.'
                );

                return $productImage;
            });
        } catch (\Throwable $exception) {
            $this->deleteFile($filePath);

            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'id' => $productImage->id,
            'path' => asset($filePath),
            'message' => 'Image uploaded successfully.',
        ]);
    }

    public function destroyImage(int $id, ProductModerationService $moderation)
    {
        $image = ProductImage::findOrFail($id);
        $product = Product::findOrFail($image->product_id);
        $path = $image->path;

        DB::transaction(function () use ($image, $product, $moderation): void {
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $image->delete();

            $this->recordAdminMaterialChange(
                $product,
                $moderation,
                'Product image deleted by an administrator.'
            );
        });

        $this->deleteFile($path);

        return response()->json(['status' => 'success', 'message' => 'Image deleted successfully.']);
    }

    public function imagesReorder(Request $request, ProductModerationService $moderation)
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:100'],
            'images.*.id' => ['required', 'integer', 'distinct', 'exists:product_images,id'],
            'images.*.order' => ['required', 'integer', 'min:0', 'max:10000', 'distinct'],
        ]);

        $imageIds = collect($validated['images'])->pluck('id')->map(fn ($id): int => (int) $id);
        $storedImages = ProductImage::query()->whereIn('id', $imageIds)->get()->keyBy('id');

        if ($storedImages->count() !== $imageIds->count()) {
            throw ValidationException::withMessages(['images' => 'One or more images are invalid.']);
        }

        $productIds = $storedImages->pluck('product_id')->unique()->values();

        if ($productIds->count() !== 1) {
            throw ValidationException::withMessages([
                'images' => 'All reordered images must belong to the same product.',
            ]);
        }

        $product = Product::findOrFail((int) $productIds->first());

        DB::transaction(function () use ($validated, $storedImages, $product, $moderation): void {
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $changed = false;

            foreach ($validated['images'] as $image) {
                $storedImage = $storedImages->get((int) $image['id']);

                if ((int) $storedImage->order === (int) $image['order']) {
                    continue;
                }

                $storedImage->update(['order' => $image['order']]);
                $changed = true;
            }

            if ($changed) {
                $this->recordAdminMaterialChange(
                    $product,
                    $moderation,
                    'Product images reordered by an administrator.'
                );
            }
        });

        return response()->noContent();
    }

    public function update(
        ProductUpdateRequest $request,
        int $id,
        ProductModerationService $moderation
    ) {
        $reviewer = Auth::guard('admin')->user();
        abort_unless($reviewer instanceof Admin, 401);

        $product = DB::transaction(function () use ($request, $id, $moderation, $reviewer) {
            $product = Product::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $expectedVersion = (int) $request->validated('moderation_version');
            abort_if(
                $expectedVersion !== (int) $product->moderation_version,
                409,
                'A newer product version is awaiting review. Reload before making a decision.'
            );
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

            $reason = trim((string) $request->input('approval_reason'));
            $reason = $reason === '' ? null : $reason;

            if ($request->approved_status === Product::APPROVAL_PENDING) {
                $moderation->submit(
                    $product,
                    null,
                    $reason ?? 'Submitted for manual review by an administrator.'
                );
            } else {
                $decide = fn (int $version) => $request->approved_status === Product::APPROVAL_APPROVED
                    ? $moderation->approve($product, $reviewer, $reason, $version)
                    : $moderation->reject(
                        $product,
                        $reason ?? 'Rejected by an administrator.',
                        $reviewer,
                        $version
                    );

                $review = $decide($expectedVersion);

                if ($review === null) {
                    $moderation->submit(
                        $product,
                        null,
                        'Product content changed during administrative review.'
                    );

                    $review = $decide((int) $product->moderation_version);
                }

                abort_if($review === null, 409, 'The product changed while it was being reviewed.');
            }

            return $product;
        });

        AlertService::created();

        return response()->json([
            'id' => $product->id,
            'status' => 'success',
            'message' => 'Product updated successfully.',
            'redirect_url' => route('admin.products.index'),
        ]);
    }

    public function storeAttributes(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ) {
        $maxValuesPerAttribute = $this->variantLimit('max_values_per_attribute');

        $validated = $request->validate([
            'attribute_id' => ['nullable', 'integer'],
            'attribute_name' => ['required', 'string', 'max:255'],
            'attribute_type' => ['required', 'string', 'in:text,color'],
            'label' => ['required', 'array', 'min:1', 'max:'.$maxValuesPerAttribute],
            'label.*' => ['required', 'string', 'max:255'],
            'value_id' => ['nullable', 'array', 'max:'.$maxValuesPerAttribute],
            'value_id.*' => ['nullable', 'integer', 'distinct'],
            'color_value' => ['nullable', 'array', 'max:'.$maxValuesPerAttribute],
            'color_value.*' => ['nullable', 'string', 'max:32'],
        ]);

        $result = DB::transaction(function () use ($validated, $request, $product, $moderation) {
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $attributeId = $validated['attribute_id'] ?? null;
            $isUpdate = filled($attributeId);

            if ($isUpdate) {
                $belongsToProduct = DB::table('product_attribute_values')
                    ->where('product_id', $product->id)
                    ->where('attribute_id', $attributeId)
                    ->exists();

                abort_unless($belongsToProduct, 404);
                $attribute = Attribute::findOrFail($attributeId);
                $this->assertAttributeIsNotSharedWithAnotherProduct($attribute, $product);
            } else {
                $attribute = new Attribute;
            }

            $attribute->name = $validated['attribute_name'];
            $attribute->type = $validated['attribute_type'];
            $attribute->save();

            $values = $this->syncAttributeValues($attribute, $request, $product);
            $this->regenerateProductVariants($product);

            $this->recordAdminMaterialChange(
                $product,
                $moderation,
                'Product attributes changed by an administrator.'
            );

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
                : 'Attribute created successfully.',
        ]);
    }

    private function renderProductVariants(Product $product): string
    {
        $variants = $product->variants()
            ->orderBy('id')
            ->get();

        return view('admin.product.partials.variants', compact('variants'))->render();
    }

    public function syncAttributeValues(Attribute $attribute, Request $request, Product $product): array
    {
        $this->assertAttributeIsNotSharedWithAnotherProduct($attribute, $product);

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
                $this->assertAttributeValueIsNotSharedWithAnotherProduct((int) $valueId, $product);

                $attributeValue = AttributeValue::query()
                    ->whereKey($valueId)
                    ->where('attribute_id', $attribute->id)
                    ->firstOrFail();
            } else {
                $attributeValue = new AttributeValue;
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
                'attribute_value_id' => $attributeValue->id,
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

    private function assertAttributeIsNotSharedWithAnotherProduct(
        Attribute $attribute,
        Product $product
    ): void {
        $isShared = DB::table('product_attribute_values')
            ->where('attribute_id', $attribute->getKey())
            ->where('product_id', '!=', $product->getKey())
            ->exists();

        abort_if(
            $isShared,
            403,
            'This attribute is shared with another product and cannot be edited.'
        );
    }

    private function assertAttributeValueIsNotSharedWithAnotherProduct(
        int $attributeValueId,
        Product $product
    ): void {
        $isShared = DB::table('product_attribute_values')
            ->where('attribute_value_id', $attributeValueId)
            ->where('product_id', '!=', $product->getKey())
            ->exists();

        abort_if(
            $isShared,
            403,
            'This attribute value is shared with another product and cannot be edited.'
        );
    }

    public function destroyAttribute(
        Product $product,
        Attribute $attribute,
        ProductModerationService $moderation
    ) {
        return DB::transaction(function () use ($product, $attribute, $moderation) {
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
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

            if (! $isUsedElsewhere) {
                // Primero borra sus valores
                AttributeValue::where('attribute_id', $attribute->id)->delete();
                // Luego borra el atributo
                $attribute->delete();
            }

            $this->regenerateProductVariants($product);
            $variantsHtml = $this->renderProductVariants($product);

            $this->recordAdminMaterialChange(
                $product,
                $moderation,
                'Product attribute deleted by an administrator.'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Attribute removed successfully.',
                'variants_html' => $variantsHtml,
                'has_attributes' => $product->attributes()->exists(),

            ]);
        });
    }

    public function regenerateProductVariants(Product $product)
    {
        $attributeGroups = $this->getAttributeGroups($product);
        $this->validateVariantCombinationLimits($attributeGroups);

        if ($attributeGroups->isEmpty()) {
            $this->clearExistingVariants($product);

            return;
        }

        $combinations = $this->cartesianProduct($attributeGroups);

        $this->clearExistingVariants($product);
        $this->createVariantsFromCombinations($product, $combinations);
    }

    public function updateVariants(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ) {
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
                'max:99999999.99',
                'decimal:0,2',
            ],
            'variant_special_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
                'lte:variant_price',
            ],
            'variant_manage_stock' => [
                'required',
                'boolean',
            ],
            'variant_quantity' => [
                'nullable',
                'integer',
                'min:0',
                'max:2147483647',
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

        $hasMaterialChanges = $variant->isDirty();

        DB::transaction(function () use (
            $variant,
            $hasMaterialChanges,
            $product,
            $moderation
        ): void {
            $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $variant->save();

            if ($hasMaterialChanges) {
                $this->recordAdminMaterialChange(
                    $product,
                    $moderation,
                    'Product variant changed by an administrator.'
                );
            }
        });

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

    public function clearExistingVariants(Product $product)
    {
        foreach ($product->variants as $variant) {
            DB::table('product_variant_attribute_value')
                ->where('product_variant_id', $variant->id)
                ->delete();

            $variant->delete();
        }
    }

    public function getAttributeGroups(Product $product)
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

    private function validateVariantCombinationLimits(Collection $attributeGroups): void
    {
        $maxGroups = $this->variantLimit('max_attribute_groups');
        $maxValues = $this->variantLimit('max_values_per_attribute');
        $maxCombinations = $this->variantLimit('max_combinations');

        if ($attributeGroups->count() > $maxGroups) {
            throw ValidationException::withMessages([
                'attributes' => 'Too many variant attributes.',
            ]);
        }

        $combinationCount = 1;

        foreach ($attributeGroups as $attributeValues) {
            $valueCount = is_countable($attributeValues) ? count($attributeValues) : 0;

            if ($valueCount > $maxValues) {
                throw ValidationException::withMessages([
                    'label' => 'Too many values for a variant attribute.',
                ]);
            }

            if ($valueCount === 0) {
                $combinationCount = 0;

                continue;
            }

            if ($combinationCount > intdiv($maxCombinations, $valueCount)) {
                throw ValidationException::withMessages([
                    'attributes' => 'Too many variant combinations.',
                ]);
            }

            $combinationCount *= $valueCount;
        }
    }

    private function variantLimit(string $key): int
    {
        $default = match ($key) {
            'max_values_per_attribute' => 50,
            'max_attribute_groups' => 6,
            'max_combinations' => 500,
            default => 1,
        };

        return max(1, (int) config('products.variants.'.$key, $default));
    }

    public function cartesianProduct(Collection $attributeGroups)
    {
        $this->validateVariantCombinationLimits($attributeGroups);

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

    public function createVariantsFromCombinations(Product $product, array $combinations)
    {
        foreach ($combinations as $combination) {
            $variant = $this->createSingleVariant($product, $combination);
            $this->attachAttributesToVariant($variant, $combination);
        }
    }

    public function createSingleVariant(Product $product, array $combination)
    {
        $variantName = collect($combination)->pluck('value')->implode('/');

        return ProductVariant::create([
            'product_id' => $product->id,
            'name' => $variantName,
            'price' => 0,
            'sku' => '',
            'qty' => 0,
        ]);
    }

    public function attachAttributesToVariant(ProductVariant $variant, array $combination)
    {
        foreach ($combination as $attributeValue) {
            DB::table('product_variant_attribute_value')->insert([
                'product_variant_id' => $variant->id,
                'attribute_id' => $attributeValue->attribute_id,
                'attribute_value_id' => $attributeValue->id,
            ]);
        }
    }

    public function destroy(Product $product)
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
