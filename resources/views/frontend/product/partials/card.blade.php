<article class="product-cart-wrap mb-30">
    <div class="product-img-action-wrap">
        <div class="product-img product-img-zoom">
            <a href="{{ route('products.show', $product->slug) }}">
                <img
                    class="default-img"
                    src="{{ $product->primaryImage?->controlledUrl() ?? asset('assets/frontend/dist/imgs/shop/product-1-1.jpg') }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                >
            </a>
        </div>
    </div>

    <div class="product-content-wrap">
        <h2>
            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
        </h2>

        <div>
            <span class="font-small text-muted">By {{ $product->store->name }}</span>
        </div>

        <div class="product-card-bottom">
            <div class="product-price">
                <span>{{ $product->store->currency ?? '$' }}{{ number_format((float) ($product->primaryVariant?->price ?? $product->price), 2) }}</span>
            </div>
        </div>
    </div>
</article>
