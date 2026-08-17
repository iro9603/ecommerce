@extends('frontend.layouts.app')

@section('contents')
    <section class="product-detail section-padding" aria-labelledby="product-title">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-30">
                    <img
                        class="img-fluid"
                        src="{{ asset($product->images->first()?->path ?? 'assets/frontend/dist/imgs/shop/product-1-1.jpg') }}"
                        alt="{{ $product->name }}"
                    >
                </div>

                <div class="col-md-6">
                    <h1 id="product-title">{{ $product->name }}</h1>
                    <p class="text-muted">By {{ $product->store->name }}</p>
                    <p class="product-price">
                        {{ $product->store->currency ?? '$' }}{{ number_format((float) ($product->primaryVariant?->price ?? $product->price), 2) }}
                    </p>

                    @if ($product->short_description)
                        <div class="product-summary">{{ strip_tags($product->short_description) }}</div>
                    @endif

                    <div class="product-description">{{ strip_tags($product->description) }}</div>
                </div>
            </div>
        </div>
    </section>
@endsection
