<div class="col-6 col-xxl-3 col-lg-4 col-md-6 col-sm-6">
    <div class="product-cart-wrap mb-30">
        <div class="product-img-action-wrap">
            <div class="product-img product-img-zoom">
                <a href="{{ route('products.show', $product->slug) }}" aria-label="{{ $product->name }}">
                    @forelse ($product->images as $key => $image)
                        <img class="{{ $key == 0 ? 'default-img' : 'hover-img' }}" src="{{ $image->controlledUrl() }}"
                            alt="{{ $product->name }}" loading="lazy" />
                    @empty
                        <img class="default-img" src="{{ asset('assets/frontend/dist/imgs/shop/product-1-1.jpg') }}"
                            alt="{{ $product->name }}" loading="lazy" />
                    @endforelse
                </a>
            </div>
            <div class="product-action-1">
                <a aria-label="Add To Wishlist" class="action-btn" href="shop-wishlist.html"><i
                        class="fi-rs-heart"></i></a>
                <a aria-label="Compare" class="action-btn" href="shop-compare.html"><i class="fi-rs-shuffle"></i></a>
                <a aria-label="Quick view" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickViewModal"
                    role="button" tabindex="0">
                    <i class="fi-rs-eye"></i>
                </a>
            </div>
            <div class="product-badges product-badges-position product-badges-mrg">
                @if ($product->is_hot)
                    <span class="hot">Hot</span>
                @endif
                @if ($product->is_new)
                    <span class="new ms-1">New</span>
                @endif
            </div>
        </div>
        <div class="product-content-wrap">
            <h2><a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a></h2>
            <div>
                <span class="font-small text-muted">By {{ $product->store?->name }}</span>
            </div>
            @php($cardPricing = $product->primaryVariant?->pricing() ?? $product->pricing())
            <div class="product-card-bottom">
                <div class="product-price">
                    @if ($cardPricing['effective_price'] !== null)
                        @if ($cardPricing['has_active_special'])
                            <span>{{ $product->currencySymbol() }}{{ number_format((float) $cardPricing['effective_price'], 2, '.', ',') }}</span>
                            <span
                                class="old-price">{{ $product->currencySymbol() }}{{ number_format((float) $cardPricing['regular_price'], 2, '.', ',') }}</span>
                        @else
                            <span>{{ $product->currencySymbol() }}{{ number_format((float) $cardPricing['effective_price'], 2, '.', ',') }}</span>
                        @endif
                    @else
                        <span class="text-muted">Precio no disponible</span>
                    @endif
                </div>
                <div class="add-cart">
                    <a class="add add_to-cart" data-id="{{ $product->id }}" href=""><i
                            class="fi-rs-shopping-cart mr-5"></i>Add </a>
                </div>
            </div>
        </div>
    </div>
</div>
