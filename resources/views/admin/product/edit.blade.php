@extends('admin.layouts.app')
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" type="text/css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css" />
    <style>
        .dropzone {
            border: 2px dashed #ccc;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 20px;
        }

        .dropzone.dz-drag-hover {
            border-color: #2196F3;
            background: #e3f2fd;
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-preview-item {
            position: relative;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: move;
        }

        .image-preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
        }

        .image-preview-item .remove-image {
            position: absolute;
            top: -10px;
            right: -10px;
            background: red;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            cursor: pointer;
        }

        .image-preview-loader {
            position: relative;
            width: 100%;
            height: 150px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 1.5s infinite;
        }

        .image-preview-loader::after {
            content: "Uploading...";
            color: #666;
        }

        @keyframes pulse {
            0% {
                opacity: 0.6;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.6;
            }
        }

        #product-attributes .accordion-item {
            border: 1px solid rgba(98, 105, 118, 0.16);
            border-radius: 10px;
            overflow: hidden;
        }

        #product-attributes .accordion-item+.accordion-item {
            margin-top: 12px;
        }

        #product-attributes .accordion-body {
            background: #fbfcfe;
        }

        #product-attributes .attribute-values-table {
            margin-bottom: 0;
        }

        #product-attributes .attribute-values-table th {
            background: #f1f4f8;
            color: #626976;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        #product-attributes .attribute-values-table td {
            padding: 12px;
            vertical-align: middle;
        }

        #product-attributes .attribute-color-control {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 220px;
        }

        #product-attributes .color-picker-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            padding: 3px;
            border: 1px solid rgba(98, 105, 118, 0.22);
            border-radius: 11px;
            background: #fff;
            box-shadow: 0 2px 7px rgba(24, 36, 51, 0.08);
        }

        #product-attributes .color-picker-wrap .pcr-button {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            overflow: hidden;
        }

        #product-attributes .color-details {
            display: flex;
            flex: 1;
            flex-direction: column;
            line-height: 1.2;
        }

        #product-attributes .color-value-display {
            color: #182433;
            font-family: SFMono-Regular, Consolas, "Liberation Mono", monospace;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        #product-attributes .color-help {
            margin-top: 3px;
            color: #929dab;
            font-size: 0.72rem;
        }

        #product-attributes .remove-attribute-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            padding: 0;
            border-radius: 8px;
        }

        @media (max-width: 767.98px) {
            #product-attributes .attribute-values-table thead {
                display: none;
            }

            #product-attributes .attribute-values-table,
            #product-attributes .attribute-values-table tbody,
            #product-attributes .attribute-values-table tr,
            #product-attributes .attribute-values-table td {
                display: block;
                width: 100%;
            }

            #product-attributes .attribute-values-table tr {
                border-bottom: 1px solid rgba(98, 105, 118, 0.16);
            }

            #product-attributes .attribute-values-table td {
                border: 0;
            }
        }
    </style>
@endpush
@section('contents')
    <div class="container-xl">
        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Products</h2>
                    <div class="text-muted">
                        Update your product information
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Back</a>
                </div>
            </div>
        </div>

        <form action="" class="product-form">
            @csrf
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Name</label>
                                    <input type="text" class="form-control" name="name" placeholder=""
                                        value="{{ $product->name }}" id="name">
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Slug</label>
                                    <input type="text" class="form-control" name="slug" placeholder=""
                                        value="{{ $product->slug }}" id="slug">
                                    <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Short Description</label>
                                    <textarea name="short_description" id="short-editor" cols="30" rows="10">{!! $product->short_description !!}</textarea>
                                    <x-input-error :messages="$errors->get('short_description')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Content</label>
                                    <textarea name="content" id="editor" cols="30" rows="10">{!! $product->description !!}</textarea>
                                    <x-input-error :messages="$errors->get('content')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card ">
                        <div class="disabled-placeholder" style="{{ count($product->attributes) ? '' : 'display:none' }}">
                        </div>
                        <div class="card-header">
                            Overview
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="form-label">SKU</label>
                                        <input type="text" class="form-control" name="sku"
                                            value="{{ $product->sku }}">
                                        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="form-label">Price</label>
                                        <input type="text" class="form-control" name="price"
                                            value="{{ $product->price }}">
                                        <x-input-error :messages="$errors->get('price')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="form-label">Special Price</label>
                                        <input type="text" class="form-control" name="special_price"
                                            value="{{ $product->special_price }}">
                                        <x-input-error :messages="$errors->get('special_price')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="form-label">From Date</label>
                                        <input type="text" class="form-control selector-from" name="from_date"
                                            value="{{ $product->special_price_start }}" id="datepicker-icon-prepend">
                                        <x-input-error :messages="$errors->get('from_date')" class="mt-2" />
                                    </div>

                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="form-label">To Date</label>
                                        <input type="text" class="form-control selector-to" name="to_date"
                                            value="{{ $product->special_price_end }}">
                                        <x-input-error :messages="$errors->get('to_date')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-check">
                                                <input class="form-check-input manage-stock-check" type="checkbox"
                                                    name="manage_stock" @checked($product->manage_stock == 'yes')>
                                                <span class="form-check-label">Manage Stock</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div
                                        class="col-md-12 manage-stock {{ $product->manage_stock == 'yes' ? '' : ' d-none' }}">
                                        <div class="mb-3">
                                            <label for="form-label">Quantity</label>
                                            <input type="text" class="form-control" name="quantity"
                                                value="{{ $product->qty }}">
                                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h3 class="card-title">Stock Status</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="stock_status" @checked($product->in_stock == 1)
                                                            value="in_stock">
                                                        <span class="form-check-label">In stock</span>
                                                    </label>
                                                    <label class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="stock_status" @checked($product->in_stock == 0)
                                                            value="out_of_stock">
                                                        <span class="form-check-label">Out of stock</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3" id="product-images">
                        <div class="card-header">
                            <h3 class="card-title">Product Image</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div id="imageUploader" class="dropzone"></div>
                                    <div id="imagePreviewContainer" class="image-preview-container">
                                        @foreach ($product?->images ?? [] as $image)
                                            <div class = "image-preview-item" data-image-id = "{{ $image->id }}">
                                                <img src = "{{ asset($image->path) }}">
                                                <span class="remove-image"
                                                    data-image-id="{{ $image->id }}">&times;</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3" id="product-attributes">
                        <div class="card-header">
                            <h3 class="card-title">Product Attributes</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="accordion" id="accordion-default">
                                    @foreach ($attributesWithValues as $attribute)
                                        @include('admin.product.partials.attribute', [
                                            'attribute' => $attribute,
                                            'product' => $product,
                                        ])
                                    @endforeach
                                </div>
                                <button class="btn btn-primary mt-3" type="button" id="add-attribute-btn">Add
                                    Attributes</button>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3" id="product-variants">
                        <div class="card-header">
                            <h3 class="card-title">Product Variants </h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="accordion" id="accordion-variant">
                                    @include('admin.product.partials.variants', ['variants' => $variants])
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <select name="status" class="form-control" id="">
                                        <option @selected($product->status == 'active') value="active">Active</option>
                                        <option @selected($product->status == 'inactive') value="inactive">Inactive</option>
                                        <option @selected($product->status == 'draft') value="draft">Draft</option>
                                        <option @selected($product->status == 'pending') value="pending">Pending</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Store</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <select name="store" class="form-control select2" id="">
                                        <option value="">Select a store</option>
                                        @foreach ($stores as $store)
                                            <option @selected($product->store_id == $store->id) value="{{ $store->id }}">
                                                {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('store')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Is Featured</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-check form-switch form-switch-3">
                                        <input class="form-check-input" @checked($product->is_featured == 1) type="checkbox"
                                            name="is_featured">
                                        <span class="form-check-label">Enable</span>
                                    </label>
                                    <x-input-error :messages="$errors->get('is_featured')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Categories</h3>
                        </div>
                        <div class="card-body" style="height:400px; overflow-y:scroll;">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <input type="text" class="form-control mb-4" id="category-search">
                                    <ul class="list-unstyled" id="category-tree">
                                        @foreach ($categories as $category)
                                            <li>
                                                <label for="" class="form-check category-wrapper">
                                                    <input type="checkbox" class="form-check-input category-check"
                                                        name="categories[]" value="{{ $category->id }}"
                                                        @checked(in_array($category->id, $productCategoryIds))>
                                                    <span
                                                        class="form-check-label category-label">{{ $category->name }}</span>
                                                </label>
                                                @if ($category->children_nested && $category->children_nested->count() > 0)
                                                    <ul class="list-unstyled ms-4 mt-2">
                                                        @foreach ($category->children_nested as $child)
                                                            <li>
                                                                <label for="" class="form-check category-wrapper">
                                                                    <input type="checkbox"
                                                                        class="form-check-input category-check"
                                                                        name="categories[]" value="{{ $child->id }}"
                                                                        @checked(in_array($child->id, $productCategoryIds))>
                                                                    <span
                                                                        class="form-check-label category-label">{{ $child->name }}</span>
                                                                </label>
                                                                @if ($child->children_nested && $child->children_nested->count() > 0)
                                                                    <ul class="list-unstyled ms-4 mt-2">
                                                                        @foreach ($child->children_nested as $subChild)
                                                                            <li>
                                                                                <label for=""
                                                                                    class="form-check category-wrapper">
                                                                                    <input type="checkbox"
                                                                                        class="form-check-input category-check"
                                                                                        name="categories[]"
                                                                                        value="{{ $subChild->id }}"
                                                                                        @checked(in_array($subChild->id, $productCategoryIds))>
                                                                                    <span
                                                                                        class="form-check-label category-label">{{ $subChild->name }}</span>
                                                                                </label>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Brand</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <select name="brand" class="form-control select2" id="">
                                        <option value="">Select a brand</option>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}" @selected($product->brand_id == $brand->id)>
                                                {{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Label</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div>
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_hot"
                                                @checked($product->is_hot)>
                                            <span class="form-check-label">Hot</span>
                                        </label>
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_new"
                                                @checked($product->is_new)>
                                            <span class="form-check-label">New</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-header">
                            <h3 class="card-title">Tags</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <select name="tags[]" class="form-control js-example-basic-multiple" id=""
                                        multiple="multiple">
                                        @foreach ($tags as $tag)
                                            <option @selected(in_array($tag->id, $productTagIds)) value="{{ $tag->id }}">
                                                {{ $tag->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('tags')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                        <div class="card mb-3" style="position:sticky; top:0;">
                            <div class="card-body">
                                <div class="col-md-12">
                                    <div class="mb-3 row">
                                        <button class="btn btn-primary mt-3" type="submit">Update</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr"></script>
    <script>
        $(function() {
            const pickerInstances = {};
            let uniqueCounter = 0;

            function generateUniqueID(prefix = 'picker-') {
                uniqueCounter++;
                return prefix + uniqueCounter + '-' + Date.now();
            }

            function escapeHtml(value) {
                return $('<div>').text(value ?? '').html();
            }

            function createPicker(pickerId, defaultColor = '#000000') {
                if (pickerInstances[pickerId]) {
                    pickerInstances[pickerId].destroyAndRemove();
                }

                const $preview = $(`#${pickerId}`);
                if (!$preview.length) {
                    return;
                }

                const $control = $preview.closest('.attribute-color-control');
                const $input = $control.find(`input[data-picker-id="${pickerId}"]`);
                const $display = $control.find('.color-value-display');

                function syncColor(color) {
                    const selectedColor = color ? color.toHEXA().toString().toUpperCase() : '';
                    $preview.css('background-color', selectedColor).attr('data-color', selectedColor);
                    $input.val(selectedColor);
                    $display.text(selectedColor || 'No color selected');
                }

                const picker = Pickr.create({
                    el: `#${pickerId}`,
                    theme: 'classic',
                    default: defaultColor || '#000000',
                    components: {
                        preview: true,
                        opacity: true,
                        hue: true,
                        interaction: {
                            hex: true,
                            rgba: true,
                            input: true,
                            clear: true,
                            save: true
                        }
                    }
                });

                picker.on('change', syncColor);
                picker.on('save', (color, instance) => {
                    syncColor(color);
                    instance.hide();
                });
                picker.on('clear', () => syncColor(null));

                pickerInstances[pickerId] = picker;
            }

            function destroyPicker(pickerId) {
                if (pickerInstances[pickerId]) {
                    pickerInstances[pickerId].destroyAndRemove();
                    delete pickerInstances[pickerId];
                }
            }

            function initColorPickersInContainer($container) {
                $container.find('.color-preview').each(function() {
                    const $this = $(this);
                    const pickerId = $this.attr('id');
                    const currentColor = $this.attr('data-color') || '#000000';
                    createPicker(pickerId, currentColor);
                });
            }

            function buildAttributeValueRow(type, label = '', color = '#000000', valueId = '') {
                const safeLabel = escapeHtml(label);
                const safeValueId = escapeHtml(valueId);

                if (type === 'color') {
                    const pickerId = generateUniqueID();
                    const safeColor = escapeHtml(color || '#000000');

                    return {
                        pickerId,
                        color: color || '#000000',
                        html: `
                            <tr>
                                <td>
                                    <input type="hidden" class="attribute-value-id" name="value_id[]" value="${safeValueId}">
                                    <input type="text" name="label[]" class="form-control label-input"
                                        value="${safeLabel}" placeholder="e.g. Ocean blue">
                                </td>
                                <td>
                                    <div class="attribute-color-control">
                                        <div class="color-picker-wrap">
                                            <div id="${pickerId}" class="color-preview" data-color="${safeColor}"
                                                style="background-color: ${safeColor}"></div>
                                        </div>
                                        <input type="hidden" class="color-value" data-picker-id="${pickerId}"
                                            name="color_value[]" value="${safeColor}">
                                        <div class="color-details">
                                            <span class="color-value-display">${safeColor.toUpperCase()}</span>
                                            <span class="color-help">Click the swatch to change it</span>
                                        </div>
                                        <button type="button"
                                            class="btn btn-outline-danger review-row-btn remove-attribute-row"
                                            aria-label="Remove color">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>`
                    };
                }

                return {
                    pickerId: null,
                    color: null,
                    html: `
                        <tr>
                            <td colspan="2">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="hidden" class="attribute-value-id" name="value_id[]" value="${safeValueId}">
                                    <input type="text" class="form-control label-input" name="label[]"
                                        placeholder="Label" value="${safeLabel}">
                                    <button type="button"
                                        class="btn btn-outline-danger review-row-btn remove-attribute-row"
                                        aria-label="Remove value">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>`
                };
            }

            function appendAttributeValueRow($tbody, type, label = '', color = '#000000', valueId = '') {
                const row = buildAttributeValueRow(type, label, color, valueId);
                $tbody.append(row.html);

                if (row.pickerId) {
                    createPicker(row.pickerId, row.color);
                }
            }

            let count = 0;
            $('#add-attribute-btn').on('click', function() {
                count++;
                const collapseId = 'collapse' + count;
                const headerId = 'header' + count;
                const accordionItem = `
                    <div class="accordion-item" data-index="${count}" data-attribute-id="">
                        <div class="accordion-header" id="${headerId}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#${collapseId}" aria-controls="${collapseId}" aria-expanded="false">
                                <span class="attribute-title">New Attribute #${count}</span>
                                <div class="accordion-button-toggle">
                                    <!-- Download SVG icon from http://tabler.io/icons/icon/chevron-down -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-1">
                                        <path d="M6 9l6 6l6 -6"></path>
                                    </svg>
                                </div>
                            </button>
                            <span class="delete-btn btn btn-danger" style="padding: 5px; margin-right:10px"><i
                                    class="ti ti-trash"></i></span>
                        </div>
                        <div id="${collapseId}" class="accordion-collapse collapse" data-bs-parent="#accordion-default">
                            <div class="accordion-body">
                                <div class="attribute-editor">
                                    @csrf
                                    <input type="hidden" name="attribute_id" value="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="" class="form-label">Name</label>
                                            <input type="text" class="form-control" value="" name="attribute_name">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="" class="form-label">Type</label>
                                            <select name="attribute_type" class="form-control main-type">
                                                <option value="text">Text</option>
                                                <option value="color">Color</option>
                                            </select>
                                        </div>
                                    </div>
                                    <table class="table table-bordered section-table attribute-values-table mt-3"
                                        style="display: none;">
                                        <thead>
                                            <tr>
                                                <th>Label</th>
                                                <th class="value-header">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            
                                        </tbody>
                                    </table>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-primary add-row-btn" type="button">
                                            <i class="ti ti-plus me-1"></i>Add Row
                                        </button>
                                        <button class="btn btn-sm btn-success save-btn" type="button">
                                            <i class="ti ti-device-floppy me-1"></i>Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> `;
                $('#accordion-default').append(accordionItem);
            });

            $(document).on('click', '.add-row-btn', function() {
                const $accordionBody = $(this).closest('.accordion-body');
                const type = $accordionBody.find('.main-type').val() || 'text';
                const $table = $accordionBody.find('.section-table');
                const $tbody = $table.find('tbody');

                appendAttributeValueRow($tbody, type);
                $table.show();
            });

            // remove attribute values
            $(document).on('click', '.review-row-btn', function() {
                const $row = $(this).closest('tr');
                const $colorPreview = $row.find('.color-preview');
                if ($colorPreview.length) {
                    destroyPicker($colorPreview.attr('id'));
                }
                const $table = $(this).closest('.section-table');
                $row.remove();
                if ($table.find('tbody tr').length === 0) {
                    $table.hide();
                }
            });

            // change type => rebuild rows and manage picker
            $(document).on('change', '.main-type', function() {
                const $accordionBody = $(this).closest('.accordion-body');
                const type = $(this).val();
                const $table = $accordionBody.find('.section-table');
                const $tbody = $table.find('tbody');
                const values = [];

                $tbody.find('tr').each(function() {
                    const $row = $(this);
                    const $colorPreview = $(this).find('.color-preview');

                    values.push({
                        label: $row.find('.label-input').val() || '',
                        color: $row.find('.color-value').val() || '#000000',
                        valueId: $row.find('.attribute-value-id').val() || ''
                    });

                    if ($colorPreview.length) {
                        destroyPicker($colorPreview.attr('id'));
                    }
                });

                $tbody.empty();
                values.forEach((value) => {
                    appendAttributeValueRow($tbody, type, value.label, value.color,
                        value.valueId);
                });

                $table.find('.value-header').text(type === 'color' ? 'Color' : 'Value');
                $table.toggle(values.length > 0);
            });

            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $accordionItem = $button.closest('.accordion-item');
                const attributeId = $accordionItem.data('attribute-id');

                // Atributo nuevo (aún no guardado), solo se elimina del DOM
                if (!attributeId) {
                    $accordionItem.find('.color-preview').each(function() {
                        destroyPicker($(this).attr('id'));
                    });
                    $accordionItem.remove();
                    return;
                }

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Solo ahora desactivamos el botón y hacemos la petición
                        $button.prop('disabled', true);

                        $.ajax({
                            url: "{{ route('admin.products.attributes.destroy', ['product' => $product->id, 'attribute' => '__ID__']) }}"
                                .replace('__ID__', attributeId),
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                // Destruye los pickers de color
                                $accordionItem.find('.color-preview').each(function() {
                                    destroyPicker($(this).attr('id'));
                                });
                                $accordionItem.remove();

                                if (typeof response.variants_html === 'string') {
                                    $('#accordion-variant').html(response
                                        .variants_html);
                                }

                                $('.disabled-placeholder').toggle(Boolean(response
                                    .has_attributes));

                                // Notificación de éxito
                                notyf.success(response.message);
                            },
                            error: function(xhr) {
                                const message = xhr.responseJSON?.message ||
                                    'No se pudo eliminar el atributo.';
                                Swal.fire({
                                    title: "Error!",
                                    text: message,
                                    icon: "error"
                                });
                            },
                            complete: function() {
                                $button.prop('disabled',
                                    false); // Re-habilitar por si hubo error
                            }
                        });
                    }
                });
            });

            // save attribute
            $(document).on('click', '.save-btn', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $accordionBody = $button.closest('.accordion-body');
                const $accordionItem = $button.closest('.accordion-item');
                const data = $accordionBody.find(':input[name]').serialize();
                const originalButtonHtml = $button.html();

                $button.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...'
                );

                $.ajax({
                    url: "{{ route('admin.products.attributes.store', ':id') }}".replace(':id',
                        '{{ $product->id }}'),
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    data: data,
                    success: function(response) {
                        const attribute = response.attribute;
                        const values = response.values || [];

                        $accordionBody.find('input[name="attribute_id"]').val(attribute.id);
                        $accordionItem.attr('data-attribute-id', attribute.id);
                        $accordionItem.find('.attribute-title').text(attribute.name);
                        $accordionBody.find('tbody tr').each(function(index) {
                            $(this).find('.attribute-value-id').val(values[index]?.id ||
                                '');
                        });

                        if (typeof response.variants_html === 'string') {
                            $('#accordion-variant').html(response.variants_html);
                        }

                        $('.disabled-placeholder').toggle(Boolean(response.has_attributes));

                        notyf.success(response.message);
                    },
                    error: function(xhr) {
                        const errors = xhr.responseJSON?.errors;

                        if (errors) {
                            $.each(errors, function(key, messages) {
                                notyf.error(messages[0]);
                            });
                        } else {
                            notyf.error(xhr.responseJSON?.message ||
                                'Could not save the attribute.');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).html(originalButtonHtml);
                    }
                });

            });

            // Initialize color pickers on load
            $(document).ready(function() {
                initColorPickersInContainer($('#accordion-default'));
            });

            $(document).on('change', '.variant-manage-stock', function() {
                const isChecked = $(this).is(':checked');
                const element = $(this).closest('.col-md-12').find('.variant-quantity').toggle(isChecked);
            });

            $(document)
                .off('click.variantSave', '.variant-save-btn')
                .on('click.variantSave', '.variant-save-btn', function(e) {
                    e.preventDefault();

                    const $variantContainer = $(this).closest('.variant-form');
                    const data = $variantContainer.find(':input').serialize();

                    $.ajax({
                        url: "{{ route('admin.products.variants.update', $product->id) }}",
                        method: 'POST',
                        data,
                        success(response) {
                            notyf.success(response.message);
                        },
                        error(xhr) {
                            const errors = xhr.responseJSON?.errors ?? {};

                            Object.values(errors).forEach(messages => {
                                notyf.error(messages[0]);
                            });
                        }
                    });
                });
        })
    </script>
    <script>
        $(document).on('change', '.category-check', function() {
            const isChecked = $(this).is(':checked');


            $(this).closest('li').find('input.category-check').each(function() {
                this.checked = isChecked;
                this.indeterminate = false;
                $(this).removeClass('indeterminate-parent');
            });


            function updateParents($input) {
                const $li = $input.closest('li').parent().closest('li');
                if ($li.length) {
                    const $siblings = $li.find('> ul > li input.category-check');
                    const checkedCount = $siblings.filter(':checked').length;
                    const $parent = $li.find('> label > input.category-check');

                    if (checkedCount === 0) {
                        $parent.prop('checked', false).prop('indeterminate', false).removeClass(
                            'indeterminate-parent');
                    } else if (checkedCount === $siblings.length) {
                        $parent.prop('checked', true).prop('indeterminate', false).removeClass(
                            'indeterminate-parent');
                    } else {
                        $parent.prop('checked', false).prop('indeterminate', true).addClass('indeterminate-parent');
                    }

                    updateParents($parent);
                }
            }

            updateParents($(this));
        });

        $(function() {
            $('#category-tree input.category-check:checked').trigger('change');
        });

        // search logic
        $('#category-search').on('input', function() {
            const query = $(this).val().toLowerCase();

            $('#category-tree li').each(function() {
                const label = $(this).find('> label > .category-label').text().toLowerCase();
                if (label.includes(query)) {
                    $(this).removeClass('d-none');
                    // show all ancestors
                    $(this).parents('li').removeClass('d-none');
                } else {
                    $(this).addClass('d-none');
                }
            });

            // if query is empty, show all
            if (query === '') {
                $('#category-tree li').removeClass('d-none');
            }
        });

        $('.manage-stock-check').on('change', function() {
            if ($(this).is(':checked')) {
                $('.manage-stock').removeClass('d-none');
            } else {
                $('.manage-stock').addClass('d-none');
            }
        });

        // submit form 
        $(function() {
            $('.product-form').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const data = new FormData(this);

                $.ajax({
                    method: 'POST',
                    url: "{{ route('admin.products.update', ':id') }}".replace(':id',
                        '{{ $product->id }}'),
                    data: data,
                    contentType: false,
                    processData: false,

                    success: function(response) {
                        window.location.href = response.redirect_url;

                    },

                    error: function(xhr) {
                        const errors = xhr.responseJSON?.errors;

                        if (errors) {
                            $.each(errors, function(key, messages) {
                                notyf.error(messages[0]);
                            });
                        } else {
                            notyf.error('Ocurrió un error al crear el producto.');
                            console.error(xhr.responseText);
                        }
                    }
                });
            });
        });

        // dropzone image upload
        Dropzone.autoDiscover = false;
        const imageUploader = new Dropzone("#imageUploader", {
            url: "{{ route('admin.products.images.upload', ':id') }}".replace(':id', '{{ $product->id }}'),
            paramName: "image",
            maxFilesize: 10,
            acceptedFiles: "image/*",
            addRemoveLinks: false,
            autoProcessQueue: true,
            uploadMultiple: false,
            previewsContainer: false,
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            init: function() {
                this.on('addedfile', function(file) {
                    const placeholderId = 'upload-' + Date.now();
                    addUploadPlaceholder(placeholderId);
                    file.placeholderId = placeholderId;
                });

                this.on('success', function(file, response) {
                    $(`#${file.placeholderId}`).remove();
                    addImagePreview(response.path, response.id);
                    this.removeFile(file);
                });
            }
        });

        function addUploadPlaceholder(placeholderId) {
            const placeholderHtml = `
            <div id="${ placeholderId }" class = "image-preview-item">
                <div class="image-preview-loader"></div>
            </div>
            `;

            $('#imagePreviewContainer').append(placeholderHtml);
        }

        function addImagePreview(path, id) {
            const placeholderHtml = `
            <div class = "image-preview-item" data-image-id = "${id}">
                <img src = "${path}">
                <span class="remove-image" data-image-id="${id}">&times;</span>
            </div>
            `;

            $('#imagePreviewContainer').append(placeholderHtml);
        }

        $(document).on('click', '.remove-image', function() {
            const imageId = $(this).attr('data-image-id');
            const element = this;
            $.ajax({
                method: 'DELETE',
                url: "{{ route('admin.products.images.destroy', ':id') }}".replace(':id', imageId),
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    notyf.success(response.message);
                    $(element).closest('.image-preview-item').remove();
                },
                error: function(xhr, status, error) {
                    notyf.error(error);
                }
            });

        });

        // Init sortable
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        new Sortable(imagePreviewContainer, {
            animation: 150,
            onEnd: function() {
                updateImageOrder();
            }
        });

        function updateImageOrder() {
            const imageOrder = [];
            $('.image-preview-item').each(function(index) {
                imageOrder.push({
                    id: $(this).data('image-id'),
                    order: index
                });
            });
            $.ajax({
                url: "{{ route('admin.products.images.reorder') }}",
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                data: {
                    images: imageOrder
                },
                success: function(response) {

                },
                error: function(xhr, status, error) {

                }
            });
        }
        // slug auto-generate
        $('#name').on('input', function() {

            $('#slug').val(slugify($(this).val()));

        });

        function slugify(text) {
            return text.toString().toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, '-')
                .replace(/[^a-z0-9\-]/g, '')
                .replace(/\-+/g, '-')
                .replace(/^\-+|\-+$/g, '');
        }
    </script>
@endpush
