<section class="new_arrival mt-40" aria-labelledby="new-arrivals-title">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title wow animate__animated animate__fadeIn">
                    <h3 id="new-arrivals-title">New Arrivals</h3>
                    <a class="view_all_btn" href="{{ route('products.index') }}">
                        View All <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            @forelse ($products as $product)
                <div class="col-6 col-lg-4 col-xl-3 col-xxl-2">
                    @include('frontend.product.partials.card', ['product' => $product])
                </div>
            @empty
                <div class="col-12">
                    <p class="text-muted">No products are available right now.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>
