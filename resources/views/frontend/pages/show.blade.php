@extends('frontend.layouts.app')

@push('styles')
    <style>
        .product-detail .attr-detail {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .product-detail .attr-detail strong {
            display: inline-block;
            min-width: 52px;
            width: auto;
            margin-top: 7px;
            font-size: 14px;
            color: #253d4e;
        }

        .product-detail .attribute-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .product-detail .attribute-badge {
            margin: 0;
            padding: 0;
        }

        .product-detail .attribute-option {
            min-width: 42px;
            height: 40px;
            padding: 8px 14px;
            border: 1px solid #dce3e8;
            border-radius: 8px;
            background: #fff;
            color: #253d4e;
            font-size: 14px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            transition: border-color .15s ease, background-color .15s ease, color .15s ease, box-shadow .15s ease;
        }

        .product-detail .attribute-option:hover {
            border-color: #3bb77e;
            color: #253d4e;
            box-shadow: 0 4px 12px rgba(59, 183, 126, .10);
        }

        .product-detail .attribute-option.active {
            border-color: #3bb77e;
            background: #e9f9f0;
            color: #1d8a57;
            box-shadow: 0 0 0 2px rgba(59, 183, 126, .12);
        }

        .product-detail .attribute-option.color-swatch {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            min-width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 50%;
            border: 2px solid #dce3e8;
            background-clip: padding-box;
            position: relative;
            overflow: hidden;
        }

        .product-detail .attribute-option.color-swatch.active {
            border-color: #253d4e;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #3bb77e;
        }

        .product-detail .attribute-option.color-swatch.active::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 14px;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, .35);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .product-detail .detail-qty {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            min-height: 52px;
            padding: 7px 12px;
        }

        .product-detail .detail-qty input[type="number"] {
            width: 48px;
            border: 0;
            outline: 0;
            text-align: center;
            font-weight: 700;
            color: #253d4e;
            -moz-appearance: textfield;
        }

        .product-detail .detail-qty input[type="number"]::-webkit-inner-spin-button,
        .product-detail .detail-qty input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .product-detail .qty-controls {
            display: inline-flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }

        .product-detail .detail-qty button.qty-down,
        .product-detail .detail-qty button.qty-up {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 1px solid #dce3e8;
            background: #f7f9fa;
            color: #3bb77e;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: border-color .15s ease, background-color .15s ease;
        }

        .product-detail .detail-qty button.qty-down:hover,
        .product-detail .detail-qty button.qty-up:hover {
            border-color: #3bb77e;
            background: #e9f9f0;
        }

        .product-detail .detail-qty input[type="number"]:disabled,
        .product-detail .detail-qty button:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .product-detail .button-add-to-cart:disabled {
            cursor: not-allowed;
            opacity: .55;
            box-shadow: none;
        }
    </style>
@endpush


@section('contents')
    <x-frontend.breadcrumb :items="[
        [
            'label' => 'Home',
            'url' => route('home'),
        ],
        [
            'label' => 'Products',
        ],
    ]" />

    <div class="container mb-30">
        <div class="row">
            <div class="col-xl-12">
                <div class="product-detail accordion-detail" id="product-detail"
                    data-currency-symbol="{{ $product->currencySymbol() }}"
                    data-can-purchase="{{ $product->canPurchase() ? 'true' : 'false' }}">
                    <div class="row mb-50 mt-70">
                        <div class="col-md-6 col-lg-5 col-sm-12 col-xs-12 mb-md-0 mb-sm-5">
                            <div class="detail-gallery">
                                <span class="zoom-icon"><i class="fi-rs-search"></i></span>
                                <!-- MAIN SLIDES -->
                                <div class="product-image-slider">
                                    @forelse ($product->images as $image)
                                        <figure class="border-radius-10">
                                            <img src="{{ $image->controlledUrl() }}" alt="{{ $product->name }}" />
                                        </figure>
                                    @empty
                                        <figure class="border-radius-10">
                                            <img src="{{ asset('assets/frontend/dist/imgs/shop/product-1-1.jpg') }}"
                                                alt="{{ $product->name }}" />
                                        </figure>
                                    @endforelse
                                </div>
                                <!-- THUMBNAILS -->
                                <div class="slider-nav-thumbnails">
                                    @forelse ($product->images as $image)
                                        <div><img src="{{ $image->controlledUrl() }}" alt="{{ $product->name }}" /></div>
                                    @empty
                                        <div><img src="{{ asset('assets/frontend/dist/imgs/shop/product-1-1.jpg') }}"
                                                alt="{{ $product->name }}" /></div>
                                    @endforelse
                                </div>
                            </div>
                            <!-- End Gallery -->
                        </div>
                        <div class="col-md-6 col-lg-7 col-sm-12 col-xs-12">
                            <div class="detail-info pr-30 pl-30">
                                <span class="stock-status out-stock" id="product-stock-status" aria-live="polite">
                                    {{ $product->canPurchase() ? 'In stock' : 'Agotado' }}
                                </span>
                                <h1 class="title-detail">{{ $product->name }}</h1>

                                @php
                                    $currencySymbol = $product->currencySymbol();
                                    $regularPrice = $pricing['regular_price'];
                                    $effectivePrice = $pricing['effective_price'];
                                @endphp

                                <div class="clearfix product-price-cover">
                                    <div class="product-price primary-color float-left" id="product-price"
                                        aria-live="polite">
                                        @if ($effectivePrice !== null)
                                            @if ($pricing['has_active_special'])
                                                <span
                                                    class="current-price text-brand">{{ $currencySymbol }}{{ number_format((float) $effectivePrice, 2, '.', ',') }}</span>
                                                @if ($regularPrice !== null)
                                                    <span
                                                        class="old-price font-md ml-5">{{ $currencySymbol }}{{ number_format((float) $regularPrice, 2, '.', ',') }}</span>
                                                @endif
                                            @else
                                                <span
                                                    class="current-price text-brand">{{ $currencySymbol }}{{ number_format((float) $effectivePrice, 2, '.', ',') }}</span>
                                            @endif
                                        @else
                                            <span class="current-price text-muted">Precio no disponible</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="short-desc mb-30">
                                    @if (!empty(trim((string) $safeShortDescriptionHtml)))
                                        <div class="font-lg">{!! $safeShortDescriptionHtml !!}</div>
                                    @else
                                        <p class="text-muted">No description available.</p>
                                    @endif
                                </div>

                                @forelse ($attributeGroups as $attribute)
                                    <div class="attr-detail attr-size mb-20">
                                        <strong class="mr-10">{{ $attribute->name }}: </strong>
                                        <ul class="attribute-group list-filter size-filter font-small"
                                            data-attribute="{{ $attribute->id }}">
                                            @foreach ($attribute->values as $value)
                                                @if ($attribute->type === 'color')
                                                    <li class="attribute-badge" data-value="{{ $value->id }}">
                                                        <button type="button" class="attribute-option color-swatch"
                                                            data-value="{{ $value->id }}"
                                                            style="background: {{ $value->color ?: '#000000' }}"
                                                            aria-label="{{ $value->value }}"></button>
                                                    </li>
                                                @else
                                                    <li class="attribute-badge" data-value="{{ $value->id }}">
                                                        <button type="button" class="attribute-option"
                                                            data-value="{{ $value->id }}">{{ $value->value }}</button>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                @empty
                                    <p class="text-muted">No attributes available.</p>
                                @endforelse

                                @if ($product->primaryVariant)
                                    <input type="hidden" name="variant_id" id="selected-variant" value="">
                                @endif

                                <script type="application/json" id="variants-data">@json($variantPayloads)</script>

                                {{-- <div class="detail-extralink mb-50" id="product-actions">
                                    <div class="detail-qty border radius">
                                        <input type="number" name="quantity" id="product-quantity" class="qty-val"
                                            value="1" min="1" aria-label="Quantity" />
                                        <div class="qty-controls" aria-hidden="false">
                                            <button type="button" class="qty-up" aria-label="Increase quantity">
                                                <i class="fi-rs-angle-small-up"></i>
                                            </button>
                                            <button type="button" class="qty-down" aria-label="Decrease quantity">
                                                <i class="fi-rs-angle-small-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="product-extra-link2">
                                        <button type="button" data-id = "{{ $product->id }}"
                                            class="button button-add-to-cart modal-add-to-cart" id="add-to-cart-button"
                                            disabled aria-disabled="true" title="Cart is not available yet">
                                            <i class="fi-rs-shopping-cart"></i>Add to cart
                                        </button>
                                    </div>
                                </div> --}}

                                <div class="detail-extralink mb-50" id="product-actions">
                                    <div class="detail-qty border radius">
                                        <input type="number" name="quantity" id="product-quantity" class="qty-val"
                                            value="1" min="1" aria-label="Quantity" />
                                        <div class="qty-controls" aria-hidden="false">
                                            <button type="button" class="qty-up" aria-label="Increase quantity">
                                                <i class="fi-rs-angle-small-up"></i>
                                            </button>
                                            <button type="button" class="qty-down" aria-label="Decrease quantity">
                                                <i class="fi-rs-angle-small-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="product-extra-link2">
                                        <button type="button" class="button button-add-to-cart modal-add-to-cart"
                                            id="add-to-cart-button" data-id="{{ $product->id }}">
                                            <i class="fi-rs-shopping-cart"></i>Add to cart
                                        </button>
                                    </div>
                                </div>

                                <div class="font-xs">
                                    <ul class="mr-50 float-start">
                                        <li class="mb-5">SKU:
                                            <span
                                                id="product-sku">{{ $defaultVariant?->sku ?: ($product->sku ?: 'N/A') }}</span>
                                        </li>
                                        <li class="mb-5">Tags:
                                            @forelse ($product->tags as $tag)
                                                <span rel="tag">{{ $tag->name }}</span>
                                                {{ $loop->last ? '' : ', ' }}
                                            @empty
                                                No tags
                                            @endforelse
                                        </li>
                                        <li>Stock:
                                            <span class="in-stock text-brand ml-5">
                                                <span class="stock-qty" id="product-stock">
                                                    @php($stockVariant = $defaultVariant)
                                                    @if ($stockVariant && $stockVariant->managesStock())
                                                        {{ $stockVariant->stockQuantity() }} in stock
                                                    @elseif ($stockVariant || !$product->managesStock())
                                                        {{ $product->canPurchase() ? 'In stock' : 'Agotado' }}
                                                    @else
                                                        {{ $product->stockQuantity() > 0 ? $product->stockQuantity() . ' in stock' : 'Agotado' }}
                                                    @endif
                                                </span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <!-- Detail Info -->
                        </div>
                    </div>

                    <div class="product-info">
                        <div class="tab-style3">
                            <ul class="nav nav-tabs text-uppercase">
                                <li class="nav-item">
                                    <a class="nav-link active" id="Description-tab" data-bs-toggle="tab"
                                        href="#Description">Description</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="Vendor-info-tab" data-bs-toggle="tab"
                                        href="#Vendor-info">Vendor</a>
                                </li>
                            </ul>
                            <div class="tab-content shop_info_tab entry-main-content">
                                <div class="tab-pane fade show active" id="Description">
                                    <div class="">
                                        @if (!empty(trim((string) $safeDescriptionHtml)))
                                            {!! $safeDescriptionHtml !!}
                                        @else
                                            <p class="text-muted">No description available.</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="Vendor-info">
                                    <div class="vendor-name mb-20">
                                        <h6>{{ $product->store?->name }}</h6>
                                    </div>
                                    <ul class="contact-infor mb-30">
                                        @if ($product->store?->address_line_1)
                                            <li><strong>Address: </strong>
                                                <span>{{ $product->store->address_line_1 }}</span>
                                            </li>
                                        @endif
                                        @if ($product->store?->phone)
                                            <li><strong>Phone: </strong><span>{{ $product->store->phone }}</span></li>
                                        @endif
                                        @if ($product->store?->email)
                                            <li><strong>Email: </strong><span>{{ $product->store->email }}</span></li>
                                        @endif
                                    </ul>
                                    <p>{!! $product->store?->long_description ?: $product->store?->short_description !!}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-70">
                        <div class="col-12">
                            <h2 class="section-title style-1 mb-30">Related products</h2>
                        </div>
                        <div class="col-12">
                            <div class="row related-products">
                                @forelse ($relatedProducts as $relatedProduct)
                                    <x-frontend.product-card :product="$relatedProduct" />
                                @empty
                                    <div class="col-12">
                                        <p class="text-muted">No related products found.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script>
        $(function() {
            var $productDetail = $('#product-detail');
            if (!$productDetail.length) {
                return;
            }

            var variantsData = [];
            try {
                variantsData = JSON.parse($('#variants-data').text());
            } catch (error) {
                variantsData = [];
            }

            var currencySymbol = $productDetail.data('currency-symbol') || '$';
            var $price = $productDetail.find('#product-price');
            var $stock = $productDetail.find('#product-stock');
            var $stockStatus = $productDetail.find('#product-stock-status');
            var $sku = $productDetail.find('#product-sku');
            var $quantity = $productDetail.find('#product-quantity');
            var $addToCart = $productDetail.find('#add-to-cart-button');

            function formatPrice(value) {
                return currencySymbol + Number(value).toFixed(2);
            }

            function selectedValueIds() {
                return $productDetail.find('.attribute-option.active')
                    .map(function() {
                        return parseInt($(this).attr('data-value'), 10);
                    })
                    .get()
                    .sort(function(a, b) {
                        return a - b;
                    });
            }

            function findVariant(selectedIds) {
                return variantsData.find(function(variant) {
                    var variantIds = variant.attribute_values.slice().sort(function(a, b) {
                        return a - b;
                    });

                    return selectedIds.length === variantIds.length && selectedIds.every(function(id,
                        index) {
                        return id === variantIds[index];
                    });
                }) || null;
            }

            function disableActions() {
                $quantity.prop('disabled', true).removeAttr('max');
                $addToCart.prop('disabled', true).attr('aria-disabled', 'true');
            }

            function enableQuantityForVariant(variant) {
                $quantity.prop('disabled', false);
                if (variant.manage_stock && Number(variant.stock_quantity) > 0) {
                    $quantity.attr('max', Number(variant.stock_quantity));
                } else {
                    $quantity.removeAttr('max');
                }
                $addToCart.prop('disabled', true).attr('aria-disabled', 'true');
            }

            function renderVariant(variant) {
                $sku.text(variant.sku || 'N/A');

                if (variant.effective_price === null || variant.effective_price === undefined) {
                    $price.html('<span class="current-price text-muted">Precio no disponible</span>');
                } else if (variant.has_active_special) {
                    $price.html(
                        '<span class="current-price text-brand">' + formatPrice(variant.effective_price) +
                        '</span>' +
                        '<span class="old-price font-md ml-5">' + formatPrice(variant.regular_price) + '</span>'
                    );
                } else {
                    $price.html(
                        '<span class="current-price text-brand">' + formatPrice(variant.effective_price) +
                        '</span>'
                    );
                }

                if (variant.can_purchase && variant.in_stock) {
                    $stockStatus.text('In stock');
                    if (variant.manage_stock && Number(variant.stock_quantity) > 0) {
                        $stock.text(Number(variant.stock_quantity) + ' in stock');
                    } else {
                        $stock.text('In stock');
                    }
                    enableQuantityForVariant(variant);
                } else {
                    $stockStatus.text('Agotado');
                    $stock.text('Agotado');
                    disableActions();
                }
            }

            function renderUnavailable() {
                $sku.text('N/A');
                $price.html('<span class="current-price text-muted">Combinación no disponible</span>');
                $stockStatus.text('Combinación no disponible');
                $stock.text('Combinación no disponible');
                disableActions();
            }

            function update() {
                if (variantsData.length === 0) {
                    if ($productDetail.data('can-purchase') === false) {
                        disableActions();
                    } else {
                        $quantity.prop('disabled', false);
                        $addToCart.prop('disabled', true).attr('aria-disabled', 'true');
                    }
                    return;
                }

                var variant = findVariant(selectedValueIds());
                if (variant) {
                    renderVariant(variant);
                } else {
                    renderUnavailable();
                }
            }

            function selectDefaultVariant() {
                var defaultVariant = variantsData.find(function(variant) {
                    return variant.is_default === true;
                }) || variantsData[0] || null;

                if (!defaultVariant) {
                    update();
                    return;
                }

                defaultVariant.attribute_values.forEach(function(valueId) {
                    $productDetail.find('.attribute-option[data-value="' + valueId + '"]')
                        .addClass('active')
                        .attr('aria-pressed', 'true');
                });
                update();
            }

            $productDetail.find('.attribute-option').on('click', function() {
                var $option = $(this);
                var $group = $option.closest('.attribute-group');

                $group.find('.attribute-option')
                    .removeClass('active')
                    .attr('aria-pressed', 'false');
                $option.addClass('active').attr('aria-pressed', 'true');

                update();
            });

            $productDetail.find('.qty-down').on('click', function(event) {
                event.preventDefault();
                var current = parseInt($quantity.val(), 10);
                if (isNaN(current)) {
                    current = 1;
                }
                var value = Math.max(1, current - 1);
                $quantity.val(value).trigger('change');
            });

            $productDetail.find('.qty-up').on('click', function(event) {
                event.preventDefault();
                var current = parseInt($quantity.val(), 10);
                if (isNaN(current)) {
                    current = 1;
                }
                var max = $quantity.attr('max');
                var next = current + 1;
                $quantity.val(max === undefined ? next : Math.min(parseInt(max, 10), next)).trigger(
                    'change');
            });

            selectDefaultVariant();

            $(document).on('click', '.modal-add-to-cart', function(e) {
                e.preventDefault();

                var self = $(this);
                const productId = $(this).data('id');
                const variantId = $('#selected-variant').val();
                const quantity = $('#product-quantity').val();

                $.ajax({
                    url: "{{ route('cart.add') }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        product_id: productId,
                        variant_id: variantId || null,
                        quantity: quantity || 1,
                    },
                    beforeSend: function() {
                        self.html(
                            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
                        );
                    },
                    success: function(response) {
                        if (response.status === "success" && response.modal) {
                            $('#quickViewModal').html(response.modal);
                            $('#quickViewModal').modal('show');
                        }

                        if (response.status === "success" && response.message) {
                            notyf.success(response.message);
                        }
                    },
                    error: function(error) {
                        let errors = error.responseJSON?.errors;

                        if (errors) {
                            $.each(errors, function(key, messages) {
                                notyf.error(messages[0]);
                            });
                        }
                    },
                    complete: function() {
                        self.html('<i class="fi-rs-shopping-cart mr-5"></i>Add to cart');
                    }
                });
            });
        });
    </script>
@endpush
