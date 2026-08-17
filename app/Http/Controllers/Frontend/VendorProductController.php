<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\ProductStoreRequest;
use App\Http\Requests\Vendor\ProductUpdateRequest;
use App\Http\Requests\Vendor\ReorderProductImagesRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Services\AlertService;
use App\Services\ProductContentSanitizer;
use App\Services\ProductModerationService;
use App\Traits\FileUploadTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VendorProductController extends Controller
{
    use FileUploadTrait;

    public function index(): View|RedirectResponse
    {
        Gate::authorize('viewAny', Product::class);

        $user = user();

        $products = Product::where('store_id', $user->store->id)
            ->latest()
            ->paginate(30);

        return view('vendor-dashboard.product.index', compact('products'));
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        return view('vendor-dashboard.product.create', compact('brands', 'tags', 'categories'));
    }

    public function store(
        ProductStoreRequest $request,
        string $type,
        ProductModerationService $moderation
    ) {
        abort_unless(in_array($type, ['physical', 'digital'], true), 404);
        Gate::authorize('create', Product::class);

        $product = DB::transaction(function () use ($request, $type, $moderation) {

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
            $product->store_id = $request->user()->store->id;
            $product->save();

            /** Attach categories */
            $product->categories()->sync($request->validated('categories'));

            /** Attach tags */
            $product->tags()->sync($request->validated('tags', []));

            $moderation->submit(
                $product,
                $request->user(),
                'Initial product submission by vendor.',
            );

            return $product;
        });

        if ($type == 'physical') {
            return response()->json([
                'id' => $product->id,
                'status' => 'success',
                'redirect_url' => route('vendor.products.edit', $product->id).'#product-images',
                'message' => 'Product created successfully.',
            ]);
        } else {
            return response()->json([
                'id' => $product->id,
                'status' => 'success',
                'redirect_url' => route('vendor.digital-products.edit', $product->id).'#product-images',
                'message' => 'Product created successfully.',
            ]);
        }
    }

    public function edit(Product $product, ProductContentSanitizer $sanitizer)
    {
        Gate::authorize('view', $product);
        abort_unless($product->product_type === 'physical', 404);
        $this->sanitizeContentForEditing($product, $sanitizer);

        $productCategoryIds = $product->categories->pluck('id')->toArray();
        $productTagIds = $product->tags->pluck('id')->toArray();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        $attributesWithValues = $product->attributeWithValues ?? [];
        $variants = $product?->variants ?? [];

        return view('vendor-dashboard.product.edit', compact('brands', 'tags', 'categories', 'product', 'productCategoryIds', 'productTagIds', 'attributesWithValues', 'variants'));
    }

    public function editDigital(Product $product, ProductContentSanitizer $sanitizer)
    {
        Gate::authorize('view', $product);
        abort_unless($product->product_type === 'digital', 404);
        $this->sanitizeContentForEditing($product, $sanitizer);

        $productCategoryIds = $product->categories->pluck('id')->toArray();
        $productTagIds = $product->tags->pluck('id')->toArray();
        $brands = Brand::select(['name', 'id'])->get();
        $tags = Tag::select(['name', 'id'])->get();
        $categories = Category::getNested();

        return view('vendor-dashboard.product.digital-edit', compact('brands', 'tags', 'categories', 'product', 'productCategoryIds', 'productTagIds'));
    }

    private function sanitizeContentForEditing(
        Product $product,
        ProductContentSanitizer $sanitizer
    ): void {
        $product->short_description = $sanitizer->sanitize($product->short_description);
        $product->description = $sanitizer->sanitize($product->description);
    }

    public function uploadImages(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ) {
        Gate::authorize('uploadImages', $product);

        $request->validate([
            'image' => ['required', 'image', 'max:3048'],
        ]);

        $filePath = $this->uploadFile($request->file('image'));
        abort_if($filePath === null, 422, 'The image could not be stored.');

        try {
            $productImage = DB::transaction(function () use (
                $product,
                $filePath,
                $moderation,
                $request
            ): ProductImage {
                $lockedProduct = $this->lockProductForMutation($product, 'uploadImages');

                $productImage = new ProductImage;
                $productImage->product_id = $lockedProduct->getKey();
                $productImage->path = $filePath;
                $productImage->order = ((int) ProductImage::query()
                    ->where('product_id', $lockedProduct->getKey())
                    ->max('order')) + 1;
                $productImage->save();

                $moderation->markForReview(
                    $lockedProduct,
                    $request->user(),
                    'Product images changed by vendor.',
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

    public function destroyImage(ProductImage $image, ProductModerationService $moderation)
    {
        $initialProduct = Product::query()->findOrFail($image->product_id);
        Gate::authorize('uploadImages', $initialProduct);

        $imagePath = DB::transaction(function () use ($image, $moderation): string {
            $product = Product::query()
                ->whereKey($image->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::authorize('uploadImages', $product);

            $lockedImage = ProductImage::query()
                ->whereKey($image->getKey())
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $imagePath = (string) $lockedImage->path;
            $lockedImage->delete();

            $moderation->markForReview(
                $product,
                request()->user(),
                'Product images changed by vendor.',
            );

            return $imagePath;
        });
        $this->deleteFile($imagePath);

        return response()->json(['status' => 'success', 'message' => 'Image deleted successfully.']);
    }

    public function imagesReorder(
        ReorderProductImagesRequest $request,
        ProductModerationService $moderation
    ) {
        $images = $request->validated('images');
        $firstImage = ProductImage::query()->findOrFail($images[0]['id']);
        $initialProduct = Product::query()->findOrFail($firstImage->product_id);
        Gate::authorize('reorderImages', $initialProduct);

        DB::transaction(function () use ($images, $firstImage, $request, $moderation): void {
            $product = Product::query()
                ->whereKey($firstImage->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::authorize('reorderImages', $product);

            $storedImages = ProductImage::query()
                ->where('product_id', $product->id)
                ->whereIn('id', collect($images)->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ProductImage $image): int => (int) $image->getKey());
            abort_unless($storedImages->count() === count($images), 404);
            $hasMaterialChanges = false;

            foreach ($images as $image) {
                $storedImage = $storedImages->get((int) $image['id']);
                abort_if($storedImage === null, 404);

                if ((int) $storedImage->order === (int) $image['order']) {
                    continue;
                }

                $storedImage->update(['order' => $image['order']]);
                $hasMaterialChanges = true;
            }

            if ($hasMaterialChanges) {
                $moderation->markForReview(
                    $product,
                    $request->user(),
                    'Product images reordered by vendor.',
                );
            }
        });

        return response()->noContent();
    }

    public function update(
        ProductUpdateRequest $request,
        Product $product,
        ProductModerationService $moderation
    ) {
        DB::transaction(function () use (
            $request,
            $product,
            $moderation
        ): void {
            $product = $this->lockProductForMutation($product, 'update');
            $originalCategoryIds = $product->categories()
                ->pluck('categories.id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $originalTagIds = $product->tags()
                ->pluck('tags.id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

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

            $hasMaterialChanges = $product->isDirty([
                'name',
                'slug',
                'short_description',
                'description',
                'sku',
                'price',
                'special_price',
                'special_price_start',
                'special_price_end',
                'brand_id',
                'manage_stock',
                'qty',
                'in_stock',
                'status',
            ]);

            $product->save();

            /** Attach categories */
            $categoryIds = collect($request->validated('categories'))
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $product->categories()->sync($categoryIds);

            /** Attach tags */
            $tagIds = collect($request->validated('tags', []))
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $product->tags()->sync($tagIds);

            $hasMaterialChanges = $hasMaterialChanges
                || $originalCategoryIds !== $categoryIds
                || $originalTagIds !== $tagIds;

            if ($hasMaterialChanges) {
                $moderation->markForReview(
                    $product,
                    $request->user(),
                    'Material product details changed by vendor.',
                );
            }
        });

        AlertService::created();

        return response()->json([
            'id' => $product->id,
            'status' => 'success',
            'message' => 'Product updated successfully.',
            'redirect_url' => route('vendor.products.index'),
        ]);
    }

    public function storeAttributes(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ) {
        Gate::authorize('manageAttributes', $product);
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
            $product = $this->lockProductForMutation($product, 'manageAttributes');
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

            $moderation->markForReview(
                $product,
                $request->user(),
                'Product attributes changed by vendor.',
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
        Gate::authorize('manageVariants', $product);

        $variants = $product->variants()
            ->orderBy('id')
            ->get();

        return view('vendor-dashboard.product.partials.variants', compact('variants'))->render();
    }

    public function syncAttributeValues(Attribute $attribute, Request $request, Product $product): array
    {
        Gate::authorize('manageAttributes', $product);
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
        $isSharedWithAnotherProduct = DB::table('product_attribute_values as product_values')
            ->where('product_values.attribute_id', $attribute->getKey())
            ->where('product_values.product_id', '!=', $product->getKey())
            ->exists();

        abort_if(
            $isSharedWithAnotherProduct,
            403,
            'This attribute is shared with another product and cannot be edited.'
        );
    }

    private function assertAttributeValueIsNotSharedWithAnotherProduct(
        int $attributeValueId,
        Product $product
    ): void {
        $isSharedWithAnotherProduct = DB::table('product_attribute_values as product_values')
            ->where('product_values.attribute_value_id', $attributeValueId)
            ->where('product_values.product_id', '!=', $product->getKey())
            ->exists();

        abort_if(
            $isSharedWithAnotherProduct,
            403,
            'This attribute value is shared with another product and cannot be edited.'
        );
    }

    public function destroyAttribute(
        Product $product,
        Attribute $attribute,
        ProductModerationService $moderation
    ) {
        Gate::authorize('manageAttributes', $product);

        return DB::transaction(function () use ($product, $attribute, $moderation) {
            $product = $this->lockProductForMutation($product, 'manageAttributes');
            $attribute = Attribute::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

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

            $moderation->markForReview(
                $product,
                request()->user(),
                'Product attributes changed by vendor.',
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
        Gate::authorize('manageAttributes', $product);

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
        Gate::authorize('manageVariants', $product);

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

        $variant = DB::transaction(function () use (
            $validated,
            $moderation,
            $product,
            $request
        ): ProductVariant {
            $product = $this->lockProductForMutation($product, 'manageVariants');

            // Garantiza que la variante pertenezca al producto bloqueado actual.
            $variant = $product->variants()
                ->whereKey($validated['variant_id'])
                ->lockForUpdate()
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
            $variant->save();

            if ($hasMaterialChanges) {
                $moderation->markForReview(
                    $product,
                    $request->user(),
                    'Product variants changed by vendor.',
                );
            }

            return $variant;
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
        Gate::authorize('manageVariants', $product);

        foreach ($product->variants as $variant) {
            DB::table('product_variant_attribute_value')
                ->where('product_variant_id', $variant->id)
                ->delete();

            $variant->delete();
        }
    }

    public function getAttributeGroups(Product $product)
    {
        Gate::authorize('manageAttributes', $product);

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

    public function cartesianProduct(Collection $attributeGroups): array
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
        Gate::authorize('manageVariants', $product);

        foreach ($combinations as $combination) {
            $variant = $this->createSingleVariant($product, $combination);
            $this->attachAttributesToVariant($variant, $combination);
        }
    }

    public function createSingleVariant(Product $product, array $combination)
    {
        Gate::authorize('manageVariants', $product);

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
        Gate::authorize('delete', $product);

        DB::transaction(function () use ($product): void {
            $product = $this->lockProductForMutation($product, 'delete');
            $product->delete();
        });
        notyf()->success('Product deleted successfully');

        return response()->json(['status' => 'success', 'message' => 'Product deleted successfully']);
    }

    private function lockProductForMutation(Product $product, string $ability): Product
    {
        $lockedProduct = Product::query()
            ->whereKey($product->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        Gate::authorize($ability, $lockedProduct);

        return $lockedProduct;
    }
}
