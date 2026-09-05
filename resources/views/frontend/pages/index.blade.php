@extends('frontend.layouts.app')

@section('contents')
    <x-frontend.breadcrumb :items="[
        [
            'label' => 'Home',
            'url' => '/',
        ],
        [
            'label' => 'Products',
        ],
    ]" />
    <div class="container mt-70 mb-60">
        <div class="row">
            @include('frontend.pages.partials.product-page-sidebar')
            <div class="col-lg-9 col-xxl-10">
                <div class="shop-product-fillter">
                    <div class="totall-product">
                        <p>We found <strong class="text-brand">29</strong> items for you!</p>
                    </div>
                    <div class="sort-by-product-area">
                        <div class="sort-by-cover mr-10">
                            <div class="sort-by-product-wrap">
                                <div class="sort-by">
                                    <span><i class="fi-rs-apps"></i>Show:</span>
                                </div>
                                <div class="sort-by-dropdown-wrap">
                                    <span> 50 <i class="fi-rs-angle-small-down"></i></span>
                                </div>
                            </div>
                            <div class="sort-by-dropdown">
                                <ul>
                                    <li><a class="active" href="#">50</a></li>
                                    <li><a href="#">100</a></li>
                                    <li><a href="#">150</a></li>
                                    <li><a href="#">200</a></li>
                                    <li><a href="#">All</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="sort-by-cover">
                            <div class="sort-by-product-wrap">
                                <div class="sort-by">
                                    <span><i class="fi-rs-apps-sort"></i>Sort by:</span>
                                </div>
                                <div class="sort-by-dropdown-wrap">
                                    <span> Featured <i class="fi-rs-angle-small-down"></i></span>
                                </div>
                            </div>
                            <div class="sort-by-dropdown">
                                <ul>
                                    <li><a class="active" href="#">Featured</a></li>
                                    <li><a href="#">Price: Low to High</a></li>
                                    <li><a href="#">Price: High to Low</a></li>
                                    <li><a href="#">Release Date</a></li>
                                    <li><a href="#">Avg. Rating</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row product-grid">
                    @forelse ($products as $product)
                        <x-frontend.product-card :product="$product" />
                    @empty
                        <p>No product found</p>
                    @endforelse
                    <!--end product card-->
                </div>
                <!--product grid-->
                <div class="pagination-area">
                    <nav aria-label="Page navigation example">
                        <ul class="pagination justify-content-start">
                            <li class="page-item">
                                <a class="page-link" href="#"><i class="fi-rs-arrow-small-left"></i></a>
                            </li>
                            <li class="page-item"><a class="page-link" href="#">1</a></li>
                            <li class="page-item active"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link dot" href="#">...</a></li>
                            <li class="page-item"><a class="page-link" href="#">6</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#"><i class="fi-rs-arrow-small-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- <section class="product-tabs section-padding position-relative" aria-labelledby="catalog-title">
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
    </section> --}}
@endsection

@push('scripts')
    <script>
        $(function() {
            let quickViewTrigger = null;

            $(document).on('click', '.action-btn[data-bs-target="#quickViewModal"], .add_to-cart', function() {
                quickViewTrigger = this;
            });

            $('#quickViewModal').on('hide.bs.modal', function() {
                if (quickViewTrigger) {
                    $(quickViewTrigger).focus();
                }

                // Seguridad adicional: si el foco sigue dentro del modal, sácalo.
                if (document.activeElement && this.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
            });

            $('#quickViewModal').on('hidden.bs.modal', function() {
                quickViewTrigger = null;
            });
            $('.add_to-cart').on('click', function(e) {
                e.preventDefault();
                const productId = $(this).data('id');
                /* $('#quickViewModal').modal('show'); */
                $.ajax({
                    url: "{{ route('cart.add') }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        product_id: productId
                    },
                    beforeSend: function() {

                    },
                    success: function(response) {
                        if (response.status == 'success') {
                            $('#quickViewModal').html(response.modal);
                            $('#quickViewModal').modal('show');
                        }
                    },
                    error: function(error) {
                        console.log(error);
                    },
                    complete: function() {

                    }
                });
            });


        })
    </script>
@endpush
