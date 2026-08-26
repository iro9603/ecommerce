<div class="col-6 col-xxl-3 col-lg-4 col-md-6 col-sm-6">
    <div class="product-cart-wrap mb-30">
        <div class="product-img-action-wrap">
            <div class="product-img product-img-zoom">
                <a href="{{ route('products.show', $product->slug) }}">
                    @foreach ($product->images as $key => $image)
                        <img class="{{ $key == 0 ? 'default-img' : 'hover-img' }}" src="{{ $image->controlledUrl() }}"
                            alt="" />
                    @endforeach
                    {{-- <img class="hover-img" src="assets/imgs/shop/product-1-2.jpg" alt="" /> --}}
                </a>
            </div>
            <div class="product-action-1">
                <a aria-label="Add To Wishlist" class="action-btn" href="shop-wishlist.html"><i
                        class="fi-rs-heart"></i></a>
                <a aria-label="Compare" class="action-btn" href="shop-compare.html"><i class="fi-rs-shuffle"></i></a>
                <a aria-label="Quick view" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickViewModal"><i
                        class="fi-rs-eye"></i></a>
            </div>
            <div class="product-badges product-badges-position product-badges-mrg">
                @if ($product->is_hot == 1)
                    <span class="hot">Hot</span>
                @endif
                @if ($product->is_new == 1)
                    <span class="new ms-1 ">new</span>
                @endif
            </div>
        </div>
        <div class="product-content-wrap">
            <div class="product-category">
                {{--  <a href="shop-grid-right.html">{{ $product->category }}</a> --}}
            </div>
            <h2><a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a></h2>
            <div class="product-rate-cover">
                <div class="product-rate d-inline-block">
                    <div class="product-rating" style="width: 90%"></div>
                </div>
                <span class="font-small ml-5 text-muted"> (4.0)</span>
            </div>
            <div>
                <span class="font-small text-muted">By <a
                        href="vendor-details-1.html">{{ $product->store->name }}</a></span>
            </div>
            @php
                $regularPrice = $product->primaryVariant?->price ?? $product->price;
                $specialPrice = $product->primaryVariant?->special_price ?? $product->special_price;
                $hasSpecialPrice = is_numeric($specialPrice) && (float) $specialPrice > 0;
            @endphp
            <div class="product-card-bottom">
                <div class="product-price">
                    @if ($hasSpecialPrice)
                        <span>${{ number_format((float) $specialPrice, 2, '.', ',') }}</span>
                        @if (is_numeric($regularPrice))
                            <span class="old-price">${{ number_format((float) $regularPrice, 2, '.', ',') }}</span>
                        @endif
                    @elseif (is_numeric($regularPrice))
                        <span>${{ number_format((float) $regularPrice, 2, '.', ',') }}</span>
                    @endif
                </div>
                <div class="add-cart">
                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Add
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
