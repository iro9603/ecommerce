@extends('vendor-dashboard.layouts.app')
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" type="text/css" />
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
    </style>
    @include('vendor-dashboard.product.partials.form-visuals')
@endpush
@section('contents')
    <div class="container-xl product-editor">
        <div class="page-header product-page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <div class="product-page-heading">
                        <span class="product-page-icon" aria-hidden="true"><i class="ti ti-package"></i></span>
                        <div>
                            <div class="product-eyebrow">Catalog / Physical product</div>
                            <h2 class="page-title mb-0">Create a new product</h2>
                            <div class="product-page-description">Add the essential details, pricing, inventory and
                                organization for your product.</div>
                        </div>
                    </div>
                </div>
                <div class="col-auto ms-auto">
                    <a class="btn btn-outline-secondary product-back-btn" href="{{ route('vendor.products.index') }}">
                        <i class="ti ti-arrow-left" aria-hidden="true"></i><span>Back to products</span>
                    </a>
                </div>
            </div>
        </div>

        <form action="" method="POST" class="product-form">
            @csrf
            <div class="row align-items-start">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="product-section-heading">
                                <span class="product-section-icon" aria-hidden="true"><i
                                        class="ti ti-file-description"></i></span>
                                <div>
                                    <h3 class="product-section-title">Basic information</h3>
                                    <p class="product-section-description">Give customers a clear description of the
                                        product.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Name</label>
                                    <input type="text" class="form-control" name="name" placeholder=""
                                        value="{{ old('name') }}" id="name">

                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Slug</label>
                                    <input type="text" class="form-control" name="slug" id="slug" placeholder=""
                                        value="{{ old('slug') }}">
                                    <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Short Description</label>
                                    <textarea name="short_description" id="short-editor" cols="30" rows="10"></textarea>
                                    <x-input-error :messages="$errors->get('short_description')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Content</label>
                                    <textarea name="content" id="editor" cols="30" rows="10"></textarea>
                                    <x-input-error :messages="$errors->get('content')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div class="product-section-heading">
                                <span class="product-section-icon" aria-hidden="true"><i
                                        class="ti ti-receipt-dollar"></i></span>
                                <div>
                                    <h3 class="product-section-title">Pricing &amp; inventory</h3>
                                    <p class="product-section-description">Set prices, promotions and current stock
                                        availability.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="form-label">SKU</label>
                                        <input type="text" class="form-control" name="sku" value="">
                                        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="form-label">Price</label>
                                        <input type="text" class="form-control" name="price" value="">
                                        <x-input-error :messages="$errors->get('price')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="form-label">Special Price</label>
                                        <input type="text" class="form-control" name="special_price" value="">
                                        <x-input-error :messages="$errors->get('special_price')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="form-label">From Date</label>
                                        <input type="text" class="form-control selector-from" name="from_date"
                                            value="" id="datepicker-icon-prepend">
                                        <x-input-error :messages="$errors->get('from_date')" class="mt-2" />
                                    </div>

                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="form-label">To Date</label>
                                        <input type="text" class="form-control selector-to" name="to_date"
                                            value="">
                                        <x-input-error :messages="$errors->get('to_date')" class="mt-2" />
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 stock-management-box">
                                        <div class="mb-3">
                                            <label class="form-check">
                                                <input class="form-check-input manage-stock-check" type="checkbox"
                                                    name="manage_stock">
                                                <span class="form-check-label">Manage Stock</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 manage-stock d-none">
                                        <div class="mb-3">
                                            <label for="form-label">Quantity</label>
                                            <input type="text" class="form-control" name="quantity" value="">
                                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="card mb-3 stock-status-card">
                                        <div class="card-header">
                                            <h3 class="card-title">Stock Status</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="col-md-12">
                                                <div class="stock-status-options">
                                                    <label class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="stock_status" checked="" value="in_stock">
                                                        <span class="form-check-label">In stock</span>
                                                    </label>
                                                    <label class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="stock_status" checked="" value="out_of_stock">
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
                    @isset($product)
                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Product Image</h3>
                            </div>
                            <div class="card-body">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <div id="imageUploader" class="dropzone"></div>
                                        <div id="imagePreviewContainer" class="image-preview-container">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endisset
                </div>
                <div class="col-lg-4 product-sidebar">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <select name="status" class="form-control" id="">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Categories</h3>
                        </div>
                        <div class="card-body category-scroll">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <input type="text" class="form-control mb-4" id="category-search">
                                    <ul class="list-unstyled" id="category-tree">
                                        @foreach ($categories as $category)
                                            <li>
                                                <label for="" class="form-check category-wrapper">
                                                    <input type="checkbox" class="form-check-input category-check"
                                                        name="categories[]" value="{{ $category->id }}">
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
                                                                        name="categories[]" value="{{ $child->id }}">
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
                                                                                        value="{{ $subChild->id }}">
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
                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Tags</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <select name="tags[]" class="form-control js-example-basic-multiple" id=""
                                        multiple="multiple">
                                        @foreach ($tags as $tag)
                                            <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('tags')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3 product-actions-card">
                        <div class="card-body">
                            <div class="col-md-12">
                                <div class="mb-3 row">
                                    <button class="btn btn-primary mt-3 product-submit-btn" type="submit">
                                        <i class="ti ti-plus" aria-hidden="true"></i> Create product
                                    </button>
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

                let form = $(this);
                let data = new FormData(this);

                $.ajax({
                    method: 'POST',
                    url: "{{ route('vendor.products.store', ['type' => ':type']) }}".replace(
                        ':type', '{{ request()->type }}'),
                    data: data,
                    contentType: false,
                    processData: false,

                    success: function(response) {
                        if (response.status == 'success') {
                            window.location.href = response.redirect_url;
                        }
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

        @isset($product)
            // dropzone image upload
            Dropzone.autoDiscover = false;
            const imageUploader = new Dropzone("#imageUploader", {
                url: "{{ route('vendor.products.images.upload', ':id') }}".replace(':id', '{{ $product->id }}'),
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
                    url: "{{ route('vendor.products.images.destroy', ':id') }}".replace(':id', imageId),
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
                    url: "{{ route('vendor.products.images.reorder') }}",
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
        @endisset
        // slug auto-generate
        $('#name').on('input', function() {
            if (!$('#category-id').val()) {
                $('#slug').val(slugify($(this).val()));
            }
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
