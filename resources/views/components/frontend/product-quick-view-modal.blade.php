<style id="cart-product-modal-styles">
    .cart-product-modal {
        --cart-modal-accent: #3bb77e;
        --cart-modal-accent-dark: #1d8a57;
        --cart-modal-heading: #253d4e;
        --cart-modal-border: #dce3e8;
        --cart-modal-muted: #7e8b96;
        width: calc(100% - 32px);
        max-width: 1080px;
        margin: 24px auto;
    }

    .cart-product-modal .modal-content {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(37, 61, 78, .22);
    }

    .cart-product-modal .modal-body {
        max-height: calc(100vh - 48px);
        padding: 32px;
        overflow-x: hidden;
        overflow-y: auto;
    }

    .cart-product-modal .modal-body>.row {
        align-items: flex-start;
        --bs-gutter-x: 32px;
    }

    .cart-product-modal .btn-close {
        position: absolute;
        top: 14px;
        right: 14px;
        z-index: 30;
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, .96);
        box-shadow: 0 3px 14px rgba(37, 61, 78, .18);
        opacity: .85;
    }

    .cart-product-modal .btn-close:hover,
    .cart-product-modal .btn-close:focus {
        opacity: 1;
    }

    .cart-product-modal .detail-gallery {
        position: relative;
        width: 100%;
    }

    .cart-product-modal .product-image-slider {
        overflow: hidden;
        border: 1px solid #edf1f3;
        border-radius: 14px;
        background: #f8fafb;
    }

    .cart-product-modal .product-image-slider figure {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        aspect-ratio: 1 / 1;
        margin: 0;
        padding: 18px;
        background: #fff;
    }

    .cart-product-modal .product-image-slider img {
        display: block;
        width: 100%;
        height: 100%;
        max-height: 480px;
        object-fit: contain;
    }

    .cart-product-modal .slider-nav-thumbnails {
        margin-top: 12px;
    }

    .cart-product-modal .slider-nav-thumbnails img {
        display: block;
        width: 70px;
        height: 70px;
        margin: 0 auto;
        padding: 4px;
        border: 1px solid var(--cart-modal-border);
        border-radius: 8px;
        background: #fff;
        object-fit: cover;
    }

    .cart-product-modal .detail-info {
        padding: 4px 4px 0 12px !important;
    }

    .cart-product-modal .title-detail {
        margin: 10px 44px 12px 0;
        color: var(--cart-modal-heading);
        font-size: clamp(26px, 3vw, 38px);
        line-height: 1.15;
        overflow-wrap: anywhere;
    }

    .cart-product-modal .stock-status {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    .cart-product-modal .stock-status.in-stock {
        background: #e9f9f0;
        color: var(--cart-modal-accent-dark);
    }

    .cart-product-modal .stock-status.out-stock {
        background: #fff1f0;
        color: #d33c32;
    }

    .cart-product-modal .product-price-cover {
        margin-bottom: 18px;
    }

    .cart-product-modal .product-price {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 8px;
        float: none !important;
    }

    .cart-product-modal .current-price {
        color: var(--cart-modal-accent);
        font-size: clamp(28px, 3vw, 36px);
        font-weight: 800;
        line-height: 1.1;
    }

    .cart-product-modal .old-price {
        color: #9aa5ad;
        font-size: 16px;
        text-decoration: line-through;
    }

    .cart-product-modal .short-desc {
        margin-bottom: 24px !important;
        color: #5b6b77;
        line-height: 1.65;
    }

    .cart-product-modal .short-desc> :last-child,
    .cart-product-modal .short-desc .font-lg> :last-child {
        margin-bottom: 0;
    }

    .cart-product-modal .attr-detail {
        display: grid;
        grid-template-columns: minmax(72px, auto) minmax(0, 1fr);
        align-items: start;
        gap: 12px;
        margin-bottom: 18px !important;
    }

    .cart-product-modal .attr-detail strong {
        display: block;
        margin: 11px 0 0 !important;
        color: var(--cart-modal-heading);
        font-size: 14px;
        line-height: 1.2;
    }

    .cart-product-modal .attribute-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-width: 0;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .cart-product-modal .attribute-badge {
        margin: 0;
        padding: 0;
    }

    .cart-product-modal .attribute-option {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        height: 40px;
        padding: 8px 14px;
        border: 1px solid var(--cart-modal-border);
        border-radius: 8px;
        background: #fff;
        color: var(--cart-modal-heading);
        font: inherit;
        font-size: 14px;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        appearance: none;
        transition: border-color .15s ease, background-color .15s ease,
            color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .cart-product-modal .attribute-option:hover {
        border-color: var(--cart-modal-accent);
        color: var(--cart-modal-heading);
        box-shadow: 0 4px 12px rgba(59, 183, 126, .10);
        transform: translateY(-1px);
    }

    .cart-product-modal .attribute-option:focus-visible,
    .cart-product-modal .detail-qty button:focus-visible,
    .cart-product-modal .button-add-to-cart:focus-visible {
        outline: 3px solid rgba(59, 183, 126, .25);
        outline-offset: 2px;
    }

    .cart-product-modal .attribute-option.active,
    .cart-product-modal .attribute-badge.active .attribute-option {
        border-color: var(--cart-modal-accent);
        background: #e9f9f0;
        color: var(--cart-modal-accent-dark);
        box-shadow: 0 0 0 2px rgba(59, 183, 126, .12);
    }

    .cart-product-modal .attribute-option:disabled {
        cursor: not-allowed;
        opacity: .4;
        transform: none;
    }

    .cart-product-modal .attribute-option.color-swatch {
        position: relative;
        width: 40px;
        min-width: 40px;
        height: 40px;
        padding: 0;
        overflow: hidden;
        border: 2px solid var(--cart-modal-border);
        border-radius: 50%;
        background-clip: padding-box;
    }

    .cart-product-modal .attribute-option.color-swatch.active,
    .cart-product-modal .attribute-badge.active .attribute-option.color-swatch {
        border-color: var(--cart-modal-heading);
        box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--cart-modal-accent);
    }

    .cart-product-modal .attribute-option.color-swatch.active::after,
    .cart-product-modal .attribute-badge.active .attribute-option.color-swatch::after {
        content: "\f00c";
        position: absolute;
        top: 50%;
        left: 50%;
        color: #fff;
        font-family: "Font Awesome 6 Free", "Font Awesome 5 Free";
        font-size: 14px;
        font-weight: 900;
        text-shadow: 0 1px 2px rgba(0, 0, 0, .45);
        transform: translate(-50%, -50%);
    }

    .cart-product-modal .detail-extralink {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
        gap: 12px;
        margin: 28px 0 !important;
    }

    .cart-product-modal .detail-qty {
        display: grid;
        grid-template-columns: minmax(42px, 1fr) 24px;
        width: 96px;
        height: 52px;
        min-height: 52px;
        padding: 5px 5px 5px 10px;
        overflow: hidden;
        border: 1px solid var(--cart-modal-border) !important;
        border-radius: 8px !important;
        background: #fff;
        box-sizing: border-box;
    }

    .cart-product-modal .detail-qty input[type="number"] {
        width: 100%;
        min-width: 0;
        height: 40px;
        padding: 0;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--cart-modal-heading);
        font-size: 15px;
        font-weight: 700;
        text-align: center;
        appearance: textfield;
        -moz-appearance: textfield;
    }

    .cart-product-modal .detail-qty input[type="number"]::-webkit-inner-spin-button,
    .cart-product-modal .detail-qty input[type="number"]::-webkit-outer-spin-button {
        margin: 0;
        -webkit-appearance: none;
    }

    .cart-product-modal .qty-controls {
        display: grid;
        grid-template-rows: 1fr 1fr;
        min-width: 0;
        height: 40px;
        border-left: 1px solid #edf1f3;
    }

    .cart-product-modal .detail-qty button.qty-down,
    .cart-product-modal .detail-qty button.qty-up {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 23px;
        height: 20px;
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--cart-modal-accent);
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease;
    }

    .cart-product-modal .detail-qty button.qty-up {
        border-bottom: 1px solid #edf1f3;
    }

    .cart-product-modal .detail-qty button.qty-down:hover,
    .cart-product-modal .detail-qty button.qty-up:hover {
        background: #e9f9f0;
        color: var(--cart-modal-accent-dark);
    }

    .cart-product-modal .detail-qty input[type="number"]:disabled,
    .cart-product-modal .detail-qty button:disabled {
        cursor: not-allowed;
        opacity: .45;
    }

    .cart-product-modal .product-extra-link2 {
        flex: 1 1 190px;
        min-width: 0;
    }

    .cart-product-modal .button-add-to-cart {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        min-height: 52px;
        margin: 0;
        padding: 12px 20px;
        border: 0;
        border-radius: 8px;
        background: var(--cart-modal-accent);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        line-height: 1.2;
    }

    .cart-product-modal .button-add-to-cart:disabled {
        cursor: not-allowed;
        opacity: .55;
        box-shadow: none;
    }

    .cart-product-modal .font-xs {
        clear: both;
        color: #667784;
    }

    .cart-product-modal .font-xs ul {
        display: grid;
        gap: 7px;
        width: 100%;
        margin: 0 !important;
        padding: 0;
        float: none !important;
        list-style: none;
    }

    .cart-product-modal .font-xs li {
        margin: 0 !important;
        overflow-wrap: anywhere;
    }

    @media (max-width: 767.98px) {
        .cart-product-modal {
            width: calc(100% - 20px);
            margin: 10px auto;
        }

        .cart-product-modal .modal-body {
            max-height: calc(100vh - 20px);
            padding: 22px;
        }

        .cart-product-modal .detail-gallery {
            margin-bottom: 24px;
        }

        .cart-product-modal .product-image-slider figure {
            max-height: 430px;
        }

        .cart-product-modal .detail-info {
            padding: 0 !important;
        }

        .cart-product-modal .title-detail {
            margin-right: 34px;
        }
    }

    @media (max-width: 575.98px) {
        .cart-product-modal .modal-body {
            padding: 16px;
        }

        .cart-product-modal .btn-close {
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
        }

        .cart-product-modal .title-detail {
            margin-top: 8px;
            font-size: 26px;
        }

        .cart-product-modal .attr-detail {
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .cart-product-modal .attr-detail strong {
            margin-top: 0 !important;
        }

        .cart-product-modal .detail-extralink {
            flex-direction: column;
        }

        .cart-product-modal .detail-qty,
        .cart-product-modal .product-extra-link2 {
            width: 100%;
            flex-basis: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .cart-product-modal *,
        .cart-product-modal *::before,
        .cart-product-modal *::after {
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<div class="modal-dialog modal-dialog-centered modal-xl product-detail cart-product-modal" id="product-detail"
    data-currency-symbol="{{ $product->currencySymbol() }}"
    data-can-purchase="{{ $product->canPurchase() ? 'true' : 'false' }}" role="document">
    <div class="modal-content">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 col-sm-12 col-xs-12 mb-md-0 mb-sm-5">
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
                <div class="col-md-6 col-sm-12 col-xs-12">
                    <div class="detail-info pr-30 pl-30">
                        <span class="stock-status {{ $product->canPurchase() ? 'in-stock' : 'out-stock' }}"
                            id="product-stock-status" aria-live="polite">
                            {{ $product->canPurchase() ? 'In stock' : 'Agotado' }}
                        </span>
                        <h1 class="title-detail">{{ $product->name }}</h1>

                        @php
                            $currencySymbol = $product->currencySymbol();
                            $regularPrice = $pricing['regular_price'];
                            $effectivePrice = $pricing['effective_price'];
                        @endphp

                        <div class="clearfix product-price-cover">
                            <div class="product-price primary-color float-left" id="product-price" aria-live="polite">
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

                        <script type="application/json" id="variants-data">@json($variantPayloads)</script>

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
                                <button type="button" class="button button-add-to-cart" id="add-to-cart-button"
                                    disabled aria-disabled="true" title="Cart is not available yet">
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
        </div>
    </div>
</div>
<script>
    $('#product-detail .detail-gallery').each(function() {
        var $gallery = $(this);
        var $mainSlider = $gallery.find('.product-image-slider');
        var $thumbnailSlider = $gallery.find('.slider-nav-thumbnails');

        if ($mainSlider.hasClass('slick-initialized')) {
            return;
        }
        $mainSlider.slick({
            slidesToShow: 1,
            slidesToScroll: 1,
            arrows: false,
            fade: false,
            asNavFor: $thumbnailSlider,
        });

        $thumbnailSlider.slick({
            slidesToShow: 4,
            slidesToScroll: 1,
            asNavFor: $mainSlider,
            dots: false,
            focusOnSelect: true,

            prevArrow: '<button type="button" class="slick-prev"><i class="fi-rs-arrow-small-left"></i></button>',
            nextArrow: '<button type="button" class="slick-next"><i class="fi-rs-arrow-small-right"></i></button>'
        });

        $thumbnailSlider.find('.slick-slide').removeClass('slick-active');
        $thumbnailSlider.find('.slick-slide').eq(0).addClass('slick-active');

        $mainSlider.on('beforeChange', function(event, slick, currentSlide, nextSlide) {
            $thumbnailSlider.find('.slick-slide').removeClass('slick-active');
            $thumbnailSlider.find('.slick-slide').eq(nextSlide).addClass('slick-active');
        });

        $mainSlider.on('beforeChange', function(event, slick, currentSlide, nextSlide) {
            var img = $(slick.$slides[nextSlide]).find("img");
            $gallery.find('.zoomWindowContainer,.zoomContainer').remove();
            if ($(window).width() > 768) {
                $(img).elevateZoom({
                    zoomType: "inner",
                    cursor: "crosshair",
                    zoomWindowFadeIn: 500,
                    zoomWindowFadeOut: 750
                });
            }
        });

        if ($(window).width() > 768) {
            $mainSlider.find('.slick-active img').elevateZoom({
                zoomType: "inner",
                cursor: "crosshair",
                zoomWindowFadeIn: 500,
                zoomWindowFadeOut: 750
            });
        }
    });

    //Filter color/Size
    $('.list-filter').each(function() {
        $(this).find('a').on('click', function(event) {
            event.preventDefault();
            $(this).parent().siblings().removeClass('active');
            $(this).parent().addClass('active');
            $(this).parents('.attr-detail').find('.current-size').text($(this).text());
            $(this).parents('.attr-detail').find('.current-color').text($(this).attr('data-color'));
        });
    });

    //Qty Up-Down
    $('.detail-qty').each(function() {
        var qtyval = parseInt($(this).find(".qty-val").val(), 10);
        var $qtyInput = $(this).find(".qty-val");

        $(this).find('a.qty-up').on('click', function(event) {
            event.preventDefault();
            qtyval = qtyval + 1;
            $qtyInput.val(qtyval);
        });

        $(this).find('a.qty-down').on("click", function(event) {
            event.preventDefault(); /*  */
            qtyval = Math.max(1, qtyval - 1);
            $qtyInput.val(qtyval);
        });
    });

    $('.dropdown-menu .cart_list').on('click', function(event) {
        event.stopPropagation();
    });


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
    });
</script>
