@extends('frontend.layouts.app')

@section('contents')
    <section class="product-tabs section-padding position-relative" aria-labelledby="catalog-title">
        <div class="container">
            <div class="section-title style-2">
                <h1 id="catalog-title">Products</h1>
            </div>

            <div class="row product-grid-4">
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

            <div class="mt-30">
                {{ $products->links() }}
            </div>
        </div>
    </section>
@endsection
