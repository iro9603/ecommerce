<!DOCTYPE html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8" />
    <title>ShopX - Multipurpose eCommerce HTML Template</title>
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="description" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:title" content="" />
    <meta property="og:type" content="" />
    <meta property="og:url" content="" />
    <meta property="og:image" content="" />
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/frontend/dist/imgs/theme/favicon.svg') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="{{ asset('assets/global/upload-preview/upload-preview.css') }}" />
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css"
        integrity="sha512-34s5cpvaNG3BknEWSuOncX28vz97bRI59UnVtEEpFX536A7BtZSJHsDyFoCl8S7Dt2TPzcrCEoHBGeM4SUBDBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('assets/frontend/dist/css/main.css') }}" />

    @stack('styles')
</head>

<body>

    <!-- Quick view -->
    <div class="modal fade custom-modal" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewModalLabel"
        aria-hidden="true">

    </div>

    <!--Start header-->
    @include('frontend.layouts.header')
    <!--End header-->

    <main class="main">
        @yield('contents')
    </main>

    @include('frontend.layouts.footer')
    <!--End footer-->

    <!-- Preloader Start -->
    <div id="preloader-active">
        <div class="preloader d-flex align-items-center justify-content-center">
            <div class="preloader-inner position-relative">
                <div class="text-center">
                    <img src="{{ asset('assets/frontend/dist/imgs/theme/loading.gif') }}" alt="" />
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor JS-->
    <script src="{{ asset('assets/frontend/dist/js/vendor/modernizr-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/vendor/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/vendor/jquery-migrate-3.3.0.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/vendor/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/slick.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/jquery.syotimer.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/waypoints.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/wow.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/magnific-popup.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/select2.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/counterup.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/jquery.countdown.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/images-loaded.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/isotope.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/scrollup.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/jquery.vticker-min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/jquery.theia.sticky.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/jquery.elevatezoom.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/slider-range.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/jquery-ui.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/custom-parallax.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/leaflet.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/plugins/TweenMax.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"
        integrity="sha512-LsnSViqQyaXpD4mBBdRYeP6sRwJiJveh2ZIbW41EBrNmKxgr/LFZIiWT6yr+nycvhvauz8c2nYMhrP80YhG7Cw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Template  JS -->
    <script src="{{ asset('assets/frontend/dist/js/main.js?v=6.0') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/shop.js?v=6.0') }}"></script>
    <script src="{{ asset('assets/global/upload-preview/upload-preview.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/dist/js/frontend.js') }}"></script>
    @stack('scripts')
</body>

</html>
